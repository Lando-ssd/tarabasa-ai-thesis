import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AccessibilityInfo, Animated, Easing, Pressable, StyleSheet, Text, View } from 'react-native';
import * as Speech from 'expo-speech';
import * as api from './api';
import { Dots, GameDone, GameHeader, playSfx, prepareSounds, shuffle } from './gameKit';
import { GameLoader } from './ReadGame';
import { SayText } from './SayText';
import type { SayResult } from './SayWord';
import { s } from './scale';
import { useSession } from './session';
import { Btn, Busy, Icon, IconBtn, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';

// Syllable Steps, the same rules and the same screen order as the website: the syllable method as a Practice Game. A longer word is
// taught in four steps, in the order of the method (hear and echo, split, join, read in a sentence). Free play like the other games:
// no points, no streak, no level, and what the child says is never graded or kept.
//
//   1. Listen  tap a part, hear Tara say it, four dots fill (the method's drill: repetition for automaticity)
//   2. Split   tap the clap once for each part (RL1PA-I-2, a listening skill: it needs no letters)
//   3. Join    tap the first part, then the next, in order; the tiles glide together by themselves and Tara says the word (RL1PWS-I-5)
//   4. Read    read a short sentence with the word in it: the only step that uses the microphone
//
// Only step 4 is ever scored. The parts (pic, nic, bas, ket) are not words, and the speech checker is unreliable on isolated non-words,
// so a part is never sent to it. The answer is only "heard" or "again", never "wrong".
//
// Built for children this age: one finger taps instead of dragging or sliding (a small study of 90 children aged 2 to 8 found 83 percent
// of the 7 to 8 year olds could tap and 30 percent could drag and drop); Tara SAYS every instruction (a short line of text is only the
// backup, and a speaker says it again); the next thing to tap glows; every tap answers at once. A round works as in the other games:
// three words, two or more read right the first time climb a level, up to three rounds or one round at level 3.

const MAX_ATTEMPTS = 3;
const WORDS = 3;
const CLEAN_HITS = 2;
const MAX_TRIES = 2;
const STEP_NAMES = ['Listen', 'Split', 'Join', 'Read'];
const TITLES: Record<number, string> = { 1: 'Say it with Tara', 2: 'Clap the parts', 3: 'Join the parts', 4: 'Read it in a sentence' };
const NEXT_LABELS: Record<number, string> = { 1: 'Next: split it', 2: 'Next: join it', 3: 'Next: read it' };
const ZERO_DOTS = [0, 0, 0, 0];

type Finished = { level: number; climbed: boolean; report: api.GameFinishBody };

/** The word a token asks for: lowercase, with its punctuation taken off. The same cleaning the server does. */
const clean = (token: string) => token.toLowerCase().replace(/[^a-z']/g, '');

export function SyllableSteps({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  return (
    <GameLoader load={api.gameSyllables} busyText="Getting the words ready" onExit={onExit}>
      {(data) => <SyllableGame key={learner.id} data={data} firstName={learner.firstName} onExit={onExit} />}
    </GameLoader>
  );
}

/** What Tara says (and the line shows) when a step opens. */
export function instructionFor(step: number, parts: number, repeat: number, tapMode: boolean): string {
  if (step === 1) return `Tap a part. Say it with Tara, ${repeat} times.`;
  if (step === 2) return 'Clap once for each part.';
  if (step === 3) return parts > 2 ? 'Tap the parts in order.' : 'Tap the first part. Then tap the next part.';
  return tapMode ? 'Read the sentence out loud. Then press the button.' : 'Tap the microphone. Read the sentence out loud.';
}

/**
 * Three words for a round: the child's own missed words first, then the level's words, then easier levels' words for review. A word is
 * not given twice in one sitting while there are others; the lists are short at level 2, so when they run out they start again.
 */
export function pickSyllableWords(data: api.SyllableLevels, level: number, used: Record<string, true>): api.SyllableWord[] {
  const lv = data.levels[String(level)];
  const out: api.SyllableWord[] = [];
  const take = (list: api.SyllableWord[]) => {
    for (const e of list) {
      if (out.length < WORDS && !used[e.word] && !out.some((o) => o.word === e.word)) out.push(e);
    }
  };
  take(lv.own);
  take(shuffle(lv.level));
  take(shuffle(lv.review));
  if (out.length < WORDS) {
    for (const k of Object.keys(used)) delete used[k];
    take(shuffle(lv.level));
    take(shuffle(lv.review));
  }
  out.forEach((e) => {
    used[e.word] = true;
  });
  return out;
}

function SyllableGame({ data, firstName, onExit }: { data: api.SyllableLevels; firstName: string; onExit: () => void }) {
  const repeat = data.repeat;
  const used = useRef<Record<string, true>>({});
  const [level, setLevel] = useState(data.startLevel);
  const [attempt, setAttempt] = useState(0);
  const [climbed, setClimbed] = useState(false);
  const [topCleared, setTopCleared] = useState(false);
  const [perfect, setPerfect] = useState(false);
  const [targets, setTargets] = useState<api.SyllableWord[]>(() => pickSyllableWords(data, data.startLevel, used.current));
  const [pos, setPos] = useState(0);
  const [step, setStep] = useState(1);
  const [dots, setDots] = useState<number[]>(ZERO_DOTS);
  const [claps, setClaps] = useState(0);
  const [joinTaps, setJoinTaps] = useState(0);
  const [joined, setJoined] = useState(false);
  const [gliding, setGliding] = useState(false);
  const [hits, setHits] = useState(0);
  const [tries, setTries] = useState(0);
  const [matched, setMatched] = useState<boolean[] | null>(null);
  const [caught, setCaught] = useState(false);
  const [tapMode, setTapMode] = useState(false);
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [finished, setFinished] = useState<Finished | null>(null);
  const [reduceMotion, setReduceMotion] = useState(false);
  const glide = useRef(new Animated.Value(0)).current;
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const later = (ms: number, work: () => void) => {
    timers.current.push(setTimeout(work, ms));
  };

  useEffect(() => {
    void prepareSounds();
    AccessibilityInfo.isReduceMotionEnabled()
      .then(setReduceMotion)
      .catch(() => {});
    return () => {
      timers.current.forEach(clearTimeout);
      void Speech.stop();
    };
  }, []);

  const word = targets[pos];
  const n = word?.parts.length ?? 2;
  const instruction = word ? instructionFor(step, n, repeat, tapMode) : '';

  const say = (text: string, rate = 0.75) => {
    void Speech.stop();
    Speech.speak(text, { language: 'en-US', rate });
  };

  // Tara says the instruction of a step as it opens, and again when the word changes.
  useEffect(() => {
    if (finished || !word || message) return;
    const t = setTimeout(() => say(instruction), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pos, step, attempt, tapMode, finished, message]);

  function resetWord() {
    setStep(1);
    setDots(ZERO_DOTS);
    setClaps(0);
    setJoinTaps(0);
    setJoined(false);
    setGliding(false);
    glide.setValue(0);
    setTries(0);
    setMatched(null);
    setCaught(false);
    setBusy(false);
    setNote('');
  }

  // ----- step 1: tap a part, hear it, a dot fills. Every dot on every part opens the next step.
  const allDrilled = !!word && word.parts.every((_, i) => dots[i] >= repeat);
  function tapPart(i: number) {
    say(word.parts[i], 0.6);
    if (dots[i] >= repeat) return;
    const next = dots.slice();
    next[i] += 1;
    setDots(next);
    if (next[i] >= repeat) playSfx('correct', 0.4);
    setNote(word.parts.every((_, k) => next[k] >= repeat) ? `Every part, ${repeat} times each.` : '');
  }

  // ----- step 2: one clap for each part. The word cuts apart where the parts are.
  function clap() {
    if (claps >= n) return;
    say(word.parts[claps], 0.6);
    const c = claps + 1;
    setClaps(c);
    if (c >= n) {
      setNote(`${n} parts: ${word.parts.join(' and ')}.`);
      playSfx('correct', 0.5);
    }
  }

  // ----- step 3: tap the parts in order. A part tapped out of order is never "wrong": Tara says which comes first and that tile glows.
  function tapJoin(i: number) {
    if (joined) {
      say(word.word, 0.7);
      return;
    }
    if (gliding) return;
    if (i !== joinTaps) {
      const msg = joinTaps === 0 ? 'Start with the first part.' : 'Now tap the next part.';
      setNote(msg);
      say(msg);
      return;
    }
    say(word.parts[i], 0.6);
    const t = joinTaps + 1;
    setJoinTaps(t);
    setNote('');
    if (t >= n) {
      setGliding(true);
      later(450, startGlide);
    }
  }

  // The tiles glide together by themselves once the last part is tapped, then Tara says the whole word.
  function startGlide() {
    let finished = false;
    const done = () => {
      if (finished) return;
      finished = true;
      setGliding(false);
      setJoined(true);
      setNote('You made the word!');
      playSfx('correct', 0.5);
      say(word.word, 0.7);
    };
    if (reduceMotion) {
      glide.setValue(1);
      done();
      return;
    }
    Animated.timing(glide, { toValue: 1, duration: 650, easing: Easing.inOut(Easing.quad), useNativeDriver: true }).start(() => done());
    // A screen that is not being drawn pauses animations: the word is still made, a moment later, by this timer.
    later(1150, done);
  }

  const ready = step === 1 ? allDrilled : step === 2 ? claps >= n : step === 3 ? joined : false;
  function goNext() {
    if (!ready || step >= 4) return;
    setStep(step + 1);
    setNote('');
  }

  // ----- step 4: read the sentence (the only step that listens)
  function finishWord(firstTry: boolean) {
    setBusy(true);
    const hitsNow = firstTry ? hits + 1 : hits;
    if (firstTry) setHits(hitsNow);
    playSfx('correct', 0.55);
    setCaught(true);
    const msg = firstTry ? 'Great reading!' : 'Good try! On we go.';
    setNote(msg);
    say(msg);
    later(1500, () => nextWord(pos + 1, hitsNow));
  }

  function nextWord(position: number, hitsNow: number) {
    if (position >= WORDS) {
      roundDone(hitsNow);
      return;
    }
    setPos(position);
    resetWord();
  }

  function sayResult(result: SayResult, heardWords: boolean[]) {
    if (result === 'unavailable') {
      // No microphone after all (blocked, missing, or the service cannot be reached): "I read it!".
      setTapMode(true);
      return;
    }
    if (result === 'heard') {
      setMatched(null);
      finishWord(tries === 0);
      return;
    }
    // Not sure what was said: show what went well, ask once more, then move on. There is no wrong answer here.
    const some = heardWords.some(Boolean);
    if (some) setMatched(heardWords);
    const next = tries + 1;
    setTries(next);
    if (next >= MAX_TRIES) {
      setNote('Good try! On we go.');
      setBusy(true);
      later(900, () => finishWord(false));
    } else {
      setNote(some ? 'Tara heard some of it. Try once more!' : 'Tara is not sure. Try once more!');
    }
  }

  // ----- rounds and the end, the same as the other games
  function roundDone(hitsNow: number) {
    const atCeiling = level >= 3;
    const top = topCleared || atCeiling;
    const perfectNow = perfect || hitsNow === WORDS;
    const up = hitsNow >= CLEAN_HITS && level < 3;
    const climbedNow = climbed || up;
    const attemptNow = attempt + 1;
    const nextLevel = up ? level + 1 : level;
    setTopCleared(top);
    setPerfect(perfectNow);
    setClimbed(climbedNow);

    if (attemptNow >= MAX_ATTEMPTS || atCeiling) {
      setFinished({
        level: nextLevel,
        climbed: climbedNow,
        report: { level_reached: nextLevel, top_level_cleared: top, had_perfect_round: perfectNow, rounds: Math.max(1, Math.min(3, attemptNow)) },
      });
      return;
    }

    playSfx('level', 0.6);
    setMessage(up ? 'Level up!' : 'Round complete!');
    later(1700, () => {
      setMessage(null);
      setAttempt(attemptNow);
      setLevel(nextLevel);
      newRound(nextLevel);
    });
  }

  function newRound(forLevel: number) {
    setTargets(pickSyllableWords(data, forLevel, used.current));
    setPos(0);
    setHits(0);
    resetWord();
  }

  function playAgain() {
    // A new game tries the microphone again: "I read it!" was only for a microphone that did not work a moment ago.
    setTapMode(false);
    setFinished(null);
    setLevel(data.startLevel);
    setAttempt(0);
    setClimbed(false);
    setTopCleared(false);
    setPerfect(false);
    used.current = {};
    newRound(data.startLevel);
  }

  if (finished) {
    return <GameDone game="syllable-steps" firstName={firstName} level={finished.level} climbed={finished.climbed} report={finished.report} onAgain={playAgain} onExit={onExit} />;
  }
  if (!word) return null;

  const tokens = word.sentence.split(/\s+/).filter((tok) => clean(tok));

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {step < 4 ? (
            <Cue on={ready}>
              <Btn label={NEXT_LABELS[step]} onPress={goNext} disabled={!ready} />
            </Cue>
          ) : tapMode ? (
            <>
              <Cue on={!busy}>
                <Btn label="I read it!" onPress={() => !busy && finishWord(tries === 0)} disabled={busy} />
              </Cue>
              <Text maxFontSizeMultiplier={1.2} style={styles.note} accessibilityLiveRegion="polite">
                {busy ? note : ''}
              </Text>
            </>
          ) : busy ? (
            <Busy text={note || 'Nice!'} />
          ) : (
            <>
              <SayText key={`${attempt}-${pos}-${tries}`} text={tokens.map(clean).join(' ')} kind="sentence" idleLabel={tries > 0 ? 'Tap the mic and try again' : 'Tap the mic and read the sentence'} onResult={sayResult} />
              <Text maxFontSizeMultiplier={1.2} style={[styles.note, tries > 0 && { color: colors.blueDark }]} accessibilityLiveRegion="polite">
                {tries > 0 ? note : ''}
              </Text>
            </>
          )}
        </View>
      }
    >
      <GameHeader onBack={onExit} level={level} label={`Round ${attempt + 1} of ${MAX_ATTEMPTS}`} />
      <View style={{ marginTop: space[4], gap: space[4] }}>
        <Small style={{ textAlign: 'center' }}>
          Word {Math.min(pos + 1, WORDS)} of {WORDS}
        </Small>
        <Dots done={pos} total={WORDS} />
      </View>

      <StepBar step={step} />

      <View style={styles.stage}>
        <Text maxFontSizeMultiplier={1.2} style={styles.title} accessibilityRole="header">
          {TITLES[step]}
        </Text>
        <View style={styles.instr}>
          <IconBtn icon="speaker-high" label="Hear Tara say it again" onPress={() => say(instruction)} />
          <Text maxFontSizeMultiplier={1.2} style={styles.instrText} accessibilityLiveRegion="polite">
            {instruction}
          </Text>
        </View>

        {step === 1 ? (
          <View style={styles.row}>
            {word.parts.map((p, i) => {
              const next = dots[i] < repeat && word.parts.findIndex((_, k) => dots[k] < repeat) === i;
              return (
                <Cue key={i} on={next} style={{ flex: 1 }}>
                  <Pressable
                    accessibilityRole="button"
                    accessibilityLabel={`Part ${i + 1}: ${p}`}
                    accessibilityHint={`Tap to hear it. ${dots[i]} of ${repeat} done.`}
                    onPress={() => tapPart(i)}
                    style={({ pressed }) => [styles.tile, dots[i] >= repeat && styles.tileFull, pressed && styles.tilePressed]}
                  >
                    <Text maxFontSizeMultiplier={1.1} style={styles.tileText}>
                      {p}
                    </Text>
                    <View style={styles.reps}>
                      {Array.from({ length: repeat }, (_, k) => (
                        <View key={k} style={[styles.rep, k < dots[i] && { backgroundColor: colors.ok }]} />
                      ))}
                    </View>
                  </Pressable>
                </Cue>
              );
            })}
          </View>
        ) : null}

        {step === 2 ? (
          <View style={{ alignItems: 'center', gap: space[10] }}>
            <View style={[styles.wordBar, { gap: claps >= n ? space[12] : 0 }]}>
              {word.parts.map((p, i) => (
                <View key={i} style={[styles.seg, claps > i && styles.segOn, claps < n && (i === 0 ? styles.segFirst : i === n - 1 ? styles.segLast : styles.segMid)]}>
                  <Text maxFontSizeMultiplier={1.1} style={[styles.tileText, claps > i && { color: colors.white }]}>
                    {p}
                  </Text>
                </View>
              ))}
            </View>
            <Cue on={claps < n} round>
              <Pressable accessibilityRole="button" accessibilityLabel="Clap" disabled={claps >= n} onPress={clap} style={({ pressed }) => [styles.clap, claps >= n && styles.clapOff, pressed && styles.tilePressed]}>
                <Icon name="hand-palm" size={s(40)} color={colors.white} />
              </Pressable>
            </Cue>
            <View style={styles.reps} accessible accessibilityLabel={`${claps} of ${n} claps`}>
              {Array.from({ length: n }, (_, k) => (
                <View key={k} style={[styles.rep, k < claps && { backgroundColor: colors.orange }]} />
              ))}
            </View>
          </View>
        ) : null}

        {step === 3 ? (
          <View style={{ alignItems: 'center', gap: space[10] }}>
            <View style={[styles.row, { gap: joined ? 0 : space[16], justifyContent: 'center' }]}>
              {word.parts.map((p, i) => {
                const first = i === 0;
                const last = i === n - 1;
                const move = glide.interpolate({ inputRange: [0, 1], outputRange: [0, (first ? 1 : last ? -1 : 0) * space[8]] });
                const cue = !joined && !gliding && joinTaps === i;
                return (
                  <Animated.View key={i} style={{ transform: [{ translateX: joined ? 0 : move }] }}>
                    <Cue on={cue}>
                      <Pressable
                        accessibilityRole="button"
                        accessibilityLabel={joined ? `${word.word}. Tap to hear it.` : `Part ${i + 1}: ${p}`}
                        onPress={() => tapJoin(i)}
                        style={({ pressed }) => [
                          styles.tile,
                          styles.joinTile,
                          (joined || joinTaps > i) && styles.tileLit,
                          joined && styles.tileFull,
                          joined && (first ? styles.segFirstMerged : last ? styles.segLastMerged : styles.segMidMerged),
                          pressed && styles.tilePressed,
                        ]}
                      >
                        {!joined ? (
                          <Text maxFontSizeMultiplier={1.1} style={styles.partNo}>
                            {i + 1}
                          </Text>
                        ) : null}
                        <Text maxFontSizeMultiplier={1.1} style={styles.tileText}>
                          {p}
                        </Text>
                      </Pressable>
                    </Cue>
                  </Animated.View>
                );
              })}
            </View>
            {joined ? <Btn label="Hear the word" icon="speaker-high" tone="ghost" compact onPress={() => say(word.word, 0.7)} /> : null}
          </View>
        ) : null}

        {step === 4 ? (
          <View style={{ gap: space[10] }}>
            <View style={[styles.panel, caught && styles.panelCaught]} accessible accessibilityLabel={`Read the sentence: ${word.sentence}`}>
              <View style={styles.words}>
                {tokens.map((token, i) => {
                  const heard = caught || !!matched?.[i];
                  const target = clean(token) === word.word;
                  return (
                    <View key={`${token}-${i}`} style={[styles.chip, target && styles.chipTarget, heard && styles.chipHeard]}>
                      <Text maxFontSizeMultiplier={1.15} style={[styles.word, heard && { color: colors.ok }, target && !heard && { color: '#8a4300' }]}>
                        {token}
                      </Text>
                    </View>
                  );
                })}
              </View>
            </View>
            <View style={{ alignItems: 'center' }}>
              <Btn label="Hear the sentence" icon="speaker-high" tone="ghost" compact onPress={() => say(word.sentence)} />
            </View>
          </View>
        ) : null}

        <Text maxFontSizeMultiplier={1.2} style={styles.stageNote} accessibilityLiveRegion="polite">
          {step < 4 ? note : ''}
        </Text>
        {message ? <Overlay text={message} big={message === 'Level up!'} /> : null}
      </View>
    </Screen>
  );
}

// ------------------------------------------------------------------ pieces

/** The four steps, with the one the child is on lit. */
function StepBar({ step }: { step: number }) {
  return (
    <View style={styles.stepBar} accessible accessibilityLabel={`Step ${step} of 4: ${STEP_NAMES[step - 1]}`}>
      {STEP_NAMES.map((name, i) => {
        const num = i + 1;
        const done = num < step;
        const cur = num === step;
        return (
          <View key={name} style={styles.stepItem}>
            <View style={[styles.stepDot, done && { backgroundColor: colors.ok }, cur && { backgroundColor: colors.orange, transform: [{ scale: 1.12 }] }]}>
              {done ? <Icon name="check" size={s(16)} color={colors.white} /> : <Text style={styles.stepNum}>{num}</Text>}
            </View>
            <Text maxFontSizeMultiplier={1.1} style={[styles.stepName, cur && { color: colors.orangeDark }]}>
              {name}
            </Text>
            {num === 4 ? (
              <Text maxFontSizeMultiplier={1.1} style={styles.stepMic}>
                Uses the mic
              </Text>
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

/**
 * The glow on whatever the child should tap next: a ring that pulses (a still ring when the phone is set to reduce motion), so the next
 * tap is always clear. It never blocks a tap.
 */
function Cue({ on, children, round, style }: { on: boolean; children: ReactNode; round?: boolean; style?: object }) {
  const pulse = useRef(new Animated.Value(0)).current;
  const [reduce, setReduce] = useState(false);

  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled()
      .then(setReduce)
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!on || reduce) {
      pulse.setValue(0);
      return;
    }
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 700, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0, duration: 700, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [on, reduce, pulse]);

  return (
    <View style={style}>
      {on ? (
        <Animated.View
          pointerEvents="none"
          style={[styles.ring, round && { borderRadius: 999 }, { opacity: reduce ? 1 : pulse.interpolate({ inputRange: [0, 1], outputRange: [0.3, 1] }) }]}
        />
      ) : null}
      {children}
    </View>
  );
}

/** A short full-stage message between rounds ("Round complete!", "Level up!"). */
function Overlay({ text, big }: { text: string; big?: boolean }) {
  return (
    <View style={styles.overlay} accessibilityLiveRegion="polite">
      <Text maxFontSizeMultiplier={1.2} style={[styles.overlayText, big && { fontSize: s(34) }]}>
        {text}
      </Text>
    </View>
  );
}

const TILE = s(34);

const styles = StyleSheet.create({
  footer: { gap: space[8], alignItems: 'stretch' },
  note: { fontFamily: fonts.uiBold, fontSize: s(16), lineHeight: s(21), color: colors.ink, textAlign: 'center', minHeight: s(21) },
  stage: { marginTop: space[8], backgroundColor: colors.sky, borderRadius: s(20), paddingVertical: space[12], paddingHorizontal: space[10], gap: space[10], overflow: 'hidden' },
  title: { fontFamily: fonts.displayMedium, fontSize: s(24), lineHeight: s(32), color: colors.ink, textAlign: 'center' },
  instr: { flexDirection: 'row', alignItems: 'center', gap: space[10] },
  instrText: { flex: 1, fontFamily: fonts.ui, fontSize: s(18), lineHeight: s(24), color: colors.ink },
  stageNote: { fontFamily: fonts.uiBold, fontSize: s(16), lineHeight: s(21), color: colors.blueDark, textAlign: 'center', minHeight: s(21) },

  stepBar: { flexDirection: 'row', justifyContent: 'space-between', marginTop: space[8] },
  stepItem: { flex: 1, alignItems: 'center', gap: space[2] },
  stepDot: { width: s(30), height: s(30), borderRadius: s(15), backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  stepNum: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.white },
  stepName: { fontFamily: fonts.uiBold, fontSize: s(13), color: colors.slate, textTransform: 'uppercase', letterSpacing: 0.6 },
  stepMic: { fontFamily: fonts.uiBold, fontSize: s(11), color: colors.ok, textTransform: 'uppercase', letterSpacing: 0.5 },

  ring: { position: 'absolute', top: -s(5), left: -s(5), right: -s(5), bottom: -s(5), borderRadius: s(24), borderWidth: s(4), borderColor: '#f6b867' },
  row: { flexDirection: 'row', gap: space[12] },
  tile: { minHeight: s(92), alignItems: 'center', justifyContent: 'center', paddingVertical: space[10], paddingHorizontal: space[8], borderRadius: s(20), backgroundColor: colors.white, borderWidth: 2, borderColor: '#d3e3f4', borderBottomWidth: s(6), borderBottomColor: '#c3d8ee', gap: space[8] },
  tilePressed: { transform: [{ translateY: s(3) }] },
  tileFull: { backgroundColor: colors.mint, borderColor: '#bfe8dc', borderBottomColor: '#9ed6c6' },
  tileLit: { backgroundColor: '#fff0d9', borderColor: '#f6b867', borderBottomColor: '#e3b53a' },
  tileText: { fontFamily: fonts.displayMedium, fontSize: TILE, lineHeight: TILE + s(8), color: colors.ink },
  partNo: { position: 'absolute', top: space[4], fontFamily: fonts.uiBold, fontSize: s(12), color: colors.slate, opacity: 0.8 },
  joinTile: { minWidth: s(88), paddingTop: space[14] },
  reps: { flexDirection: 'row', gap: space[6], justifyContent: 'center' },
  rep: { width: s(13), height: s(13), borderRadius: s(7), backgroundColor: colors.sky2 },

  wordBar: { flexDirection: 'row', justifyContent: 'center' },
  seg: { paddingVertical: space[10], paddingHorizontal: space[12], backgroundColor: colors.white, borderWidth: 2, borderColor: '#d3e3f4', borderBottomWidth: s(6), borderBottomColor: '#c3d8ee', borderRadius: s(20) },
  segOn: { backgroundColor: colors.orange, borderColor: '#d77a1a', borderBottomColor: colors.orangeDark },
  segFirst: { borderTopRightRadius: 0, borderBottomRightRadius: 0 },
  segLast: { borderTopLeftRadius: 0, borderBottomLeftRadius: 0 },
  segMid: { borderRadius: 0 },
  segFirstMerged: { borderTopRightRadius: 0, borderBottomRightRadius: 0 },
  segLastMerged: { borderTopLeftRadius: 0, borderBottomLeftRadius: 0 },
  segMidMerged: { borderRadius: 0 },
  clap: { width: s(88), height: s(88), borderRadius: s(44), backgroundColor: colors.orange, borderBottomWidth: s(6), borderBottomColor: colors.orangeDark, alignItems: 'center', justifyContent: 'center' },
  clapOff: { backgroundColor: '#b3c3d3', borderBottomColor: '#93a7bb' },

  panel: { backgroundColor: colors.white, borderRadius: s(18), borderWidth: 2, borderColor: '#d6def0', borderBottomWidth: s(5), borderBottomColor: '#c9d3e6', paddingVertical: space[14], paddingHorizontal: space[10] },
  panelCaught: { borderColor: '#f6b93b', borderBottomColor: '#e0a21d' },
  words: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', columnGap: space[4], rowGap: space[4] },
  chip: { paddingHorizontal: space[6], borderRadius: s(9) },
  chipTarget: { backgroundColor: '#ffe3b8' },
  chipHeard: { backgroundColor: colors.mint },
  word: { fontFamily: fonts.read, fontWeight: '500', fontSize: s(22), lineHeight: s(34), color: colors.ink },

  overlay: { ...StyleSheet.absoluteFill, backgroundColor: 'rgba(255,255,255,0.96)', alignItems: 'center', justifyContent: 'center', zIndex: 5, borderRadius: s(20) },
  overlayText: { fontFamily: fonts.display, fontSize: s(28), lineHeight: s(34), color: colors.orange, textAlign: 'center' },
});
