import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { Dots, GameDone, GameHeader, playSfx, prepareSounds } from './gameKit';
import { GameLoader } from './ReadGame';
import { s, SHORT_HEIGHT } from './scale';
import { useSession } from './session';
import { Cue, Overlay } from './SyllableSteps';
import { playLine, prepareVoice, stopAll } from './speech';
import { Btn, Icon, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';

// Sound Hunt, the same questions and the same words as the website: a listen and tap game for the sounds in words. No microphone. Tara asks
// "Which one rhymes with cat?" (level 1, RL1PA-I-3), "starts like" (level 2, RL1PA-I-5) or "ends like" (level 3, RL1PWS-I-3). Three cards each
// say their word when tapped; the child picks one and confirms with "That one!". Free play like the other games: no points, no streak, no level,
// and nothing tapped is graded or kept.
//
// A first miss is never "wrong": Tara says "Let's listen again". After a second miss she says the answer and moves on. A child on the Letters
// step (no written word yet) only HEARS the cards, which have a colour each; every other child hears and reads them. After an answer the written
// words always appear, with the part that matches picked out, so the sound is linked to the letters.
//
// Every question and every line Tara says is built by the server (App\Support\SoundHunt), so the phone and the website cannot ask different
// things. Every word is a whole word: a voice can say "sun" but not the single sound /s/. Tara's voice is recorded clips when every clip a line
// needs exists, and otherwise the phone's own read-aloud voice reads the whole line (see speech.ts): a line never mixes two voices.
//
// A round works as in the other games: three questions, two or more right the first time climb a level, up to three rounds or one round at level 3.

const MAX_ATTEMPTS = 3;
const QUESTIONS = 3;
const CLEAN_HITS = 2;
const MAX_TRIES = 2;

const ASK_READ: Record<api.SoundQuestion['kind'], string> = { rhyme: 'Which one rhymes with', first: 'Which one starts like', last: 'Which one ends like' };
const ASK_HEAR: Record<api.SoundQuestion['kind'], string> = { rhyme: 'Which word rhymes?', first: 'Which word starts the same?', last: 'Which word ends the same?' };

// One colour for each card, so a child who cannot read yet can still say "the yellow one".
const CARD_LOOK = [
  { bg: '#fff3c8', lip: '#d9a93f', ink: '#6b4700' },
  { bg: '#d6f6ea', lip: '#48a983', ink: '#0d5a45' },
  { bg: '#efe6ff', lip: '#a98cdc', ink: '#42237b' },
];
const OK_LOOK = { bg: '#d9f6e6', lip: '#2f9a6b', ink: '#0b5a3a' };

const LEVEL_ICON: Record<number, string> = { 1: 'plant', 2: 'leaf', 3: 'tree' };

type Finished = { level: number; climbed: boolean; report: api.GameFinishBody };

export function SoundHunt({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  return (
    <GameLoader load={api.gameSounds} busyText="Getting the words ready" onExit={onExit}>
      {(data) => <SoundGame key={learner.id} data={data} firstName={learner.firstName} onExit={onExit} />}
    </GameLoader>
  );
}

/** The word split in three: what comes before the part the question is about, that part, and what comes after. */
export function markWord(word: string, kind: api.SoundQuestion['kind'], mark: number): [string, string, string] {
  if (kind === 'rhyme') {
    const cut = Math.max(0, word.length - mark);
    return [word.slice(0, cut), word.slice(cut), ''];
  }
  if (kind === 'first') return ['', word.charAt(0), word.slice(1)];
  return [word.slice(0, -1), word.slice(-1), ''];
}

/**
 * The questions for one round: the next few of the level's list, going round again when the list runs out, so no question comes twice in a
 * sitting while there are others. `taken` remembers how far into each level's list the sitting is.
 */
export function takeRound(data: api.SoundHuntData, level: number, taken: Record<number, number>): api.SoundQuestion[] {
  const list = data.levels[String(level)] ?? [];
  const out: api.SoundQuestion[] = [];
  for (let i = 0; i < QUESTIONS && list.length; i++) {
    out.push(list[(taken[level] ?? 0) % list.length]);
    taken[level] = (taken[level] ?? 0) + 1;
  }
  return out;
}

function SoundGame({ data, firstName, onExit }: { data: api.SoundHuntData; firstName: string; onExit: () => void }) {
  const clips = data.clips ?? {};
  const reads = data.readsWords;
  const taken = useRef<Record<number, number>>({ 1: 0, 2: 0, 3: 0 });
  const [level, setLevel] = useState(data.startLevel);
  const [attempt, setAttempt] = useState(0);
  const [climbed, setClimbed] = useState(false);
  const [topCleared, setTopCleared] = useState(false);
  const [perfect, setPerfect] = useState(false);
  const [round, setRound] = useState<api.SoundQuestion[]>(() => takeRound(data, data.startLevel, taken.current));
  const [pos, setPos] = useState(0);
  const [hits, setHits] = useState(0);
  const [sel, setSel] = useState<string | null>(null);
  const [tries, setTries] = useState(0);
  const [missed, setMissed] = useState<string[]>([]);
  const [done, setDone] = useState(false);
  const [note, setNote] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [finished, setFinished] = useState<Finished | null>(null);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const later = (ms: number, work: () => void) => {
    timers.current.push(setTimeout(work, ms));
  };

  useEffect(() => {
    void prepareSounds();
    void prepareVoice();
    return () => {
      timers.current.forEach(clearTimeout);
      stopAll();
    };
  }, []);

  // A level with no questions at all (never expected) simply ends the game instead of showing an empty screen.
  useEffect(() => {
    if (!finished && round.length === 0) {
      setFinished({ level, climbed, report: { level_reached: level, top_level_cleared: topCleared, had_perfect_round: perfect, rounds: Math.max(1, Math.min(3, attempt)) } });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [round]);

  const q = round[pos];

  // Tara asks as each question opens (a moment later, so the screen is there first). Only while that question is still the one being asked.
  useEffect(() => {
    if (finished || !q || message || done) return;
    const t = setTimeout(() => playLine(q.say.ask, clips), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [attempt, pos, finished, message, round]);

  function sayWord(word: string) {
    playLine({ parts: [`w-${word}`], text: word }, clips);
  }

  function pickCard(word: string) {
    if (done) {
      sayWord(word);
      return;
    }
    setSel(word);
    sayWord(word);
  }

  function confirm() {
    if (!q || !sel || done) return;
    if (sel === q.answer) {
      setDone(true);
      if (tries === 0) setHits((h) => h + 1);
      playSfx('correct', 0.55);
      setNote(q.screen.right);
      playLine(q.say.right, clips);
      return;
    }
    // Never "wrong": the card fades, Tara says "Let's listen again", and the question is asked again. After the last try she says the answer.
    const next = tries + 1;
    setTries(next);
    setMissed((m) => [...m, sel]);
    setSel(null);
    if (next >= MAX_TRIES) {
      setDone(true);
      setNote(q.screen.reveal);
      playLine(q.say.reveal, clips);
      return;
    }
    setNote(q.screen.again);
    playLine(q.say.again, clips);
  }

  function resetQuestion() {
    setSel(null);
    setTries(0);
    setMissed([]);
    setDone(false);
    setNote('');
  }

  function goNext() {
    if (!done) return;
    stopAll();
    const position = pos + 1;
    if (position >= QUESTIONS) {
      roundDone(hits);
      return;
    }
    setPos(position);
    resetQuestion();
  }

  // ----- rounds and the end, the same as the other games
  function roundDone(hitsNow: number) {
    const atCeiling = level >= 3;
    const top = topCleared || atCeiling;
    const perfectNow = perfect || hitsNow === QUESTIONS;
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
    setRound(takeRound(data, forLevel, taken.current));
    setPos(0);
    setHits(0);
    resetQuestion();
  }

  function playAgain() {
    setFinished(null);
    setLevel(data.startLevel);
    setAttempt(0);
    setClimbed(false);
    setTopCleared(false);
    setPerfect(false);
    taken.current = { 1: 0, 2: 0, 3: 0 };
    newRound(data.startLevel);
  }

  if (finished) {
    return <GameDone game="sound-hunt" firstName={firstName} level={finished.level} climbed={finished.climbed} report={finished.report} onAgain={playAgain} onExit={onExit} />;
  }
  if (!q) return null;

  const showWords = reads || done;
  const title = reads ? `${ASK_READ[q.kind]} ${q.target}?` : ASK_HEAR[q.kind];
  const lastQuestion = pos >= QUESTIONS - 1;
  const levelName = data.levelNames?.[String(level)];

  return (
    <Screen
      footer={
        done ? (
          <Cue on>
            <Btn label={lastQuestion ? 'Finish round' : 'Next'} onPress={goNext} />
          </Cue>
        ) : (
          <Cue on={!!sel}>
            <Btn label="That one!" onPress={confirm} disabled={!sel} />
          </Cue>
        )
      }
    >
      <GameHeader onBack={onExit} level={level} label={`Round ${attempt + 1} of ${MAX_ATTEMPTS}${levelName ? ` · ${levelName}` : ''}`} icons={LEVEL_ICON} />
      <View style={{ marginTop: space[4], gap: space[4] }}>
        {/* On the smallest phones the dots (which say "Set 1 of 3" to a screen reader) are enough: the cards and the button have to stay on screen. */}
        {SHORT_HEIGHT ? null : (
          <Small style={{ textAlign: 'center' }}>
            Question {Math.min(pos + 1, QUESTIONS)} of {QUESTIONS}
          </Small>
        )}
        <Dots done={pos} total={QUESTIONS} />
      </View>

      <View style={[styles.stage, SHORT_HEIGHT && styles.stageShort]}>
        <Text maxFontSizeMultiplier={1.2} style={[styles.title, SHORT_HEIGHT && styles.titleShort]} accessibilityRole="header">
          {title}
        </Text>

        {/* The word Tara asks about is a button too: tap it to hear the question again. */}
        <View style={{ alignItems: 'center' }}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Hear the question again"
            onPress={() => playLine(q.say.ask, clips)}
            style={({ pressed }) => [styles.target, SHORT_HEIGHT && styles.targetShort, pressed && styles.pressed]}
          >
            <View style={[styles.spk, SHORT_HEIGHT && styles.spkShort]}>
              <Icon name="speaker-high" size={s(SHORT_HEIGHT ? 18 : 22)} color={colors.white} />
            </View>
            {reads || done ? (
              <Marked word={q.target} q={q} plain={!done} style={[styles.targetWord, SHORT_HEIGHT && styles.targetWordShort]} hl={styles.targetHl} underline={done} />
            ) : (
              <Text maxFontSizeMultiplier={1.15} style={styles.listen}>
                Listen
              </Text>
            )}
          </Pressable>
        </View>

        <View style={{ gap: SHORT_HEIGHT ? space[6] : space[10] }} accessibilityRole="radiogroup">
          {q.choices.map((w, i) => {
            const isAnswer = done && w === q.answer;
            const look = isAnswer ? OK_LOOK : CARD_LOOK[i % CARD_LOOK.length];
            const isMissed = missed.includes(w);
            return (
              <Pressable
                key={w}
                accessibilityRole="button"
                accessibilityLabel={showWords ? w : `Word ${i + 1}`}
                accessibilityHint="Tap to hear it."
                accessibilityState={{ selected: sel === w, disabled: isMissed }}
                disabled={isMissed}
                onPress={() => pickCard(w)}
                style={({ pressed }) => [
                  styles.card,
                  SHORT_HEIGHT && styles.cardShort,
                  { backgroundColor: look.bg, borderBottomColor: look.lip },
                  sel === w && !done && styles.cardSel,
                  done && !isAnswer && styles.dim,
                  isMissed && !done && styles.miss,
                  pressed && styles.pressed,
                ]}
              >
                <View style={[styles.spk, SHORT_HEIGHT && styles.spkShort]}>
                  <Icon name="speaker-high" size={s(SHORT_HEIGHT ? 20 : 24)} color={colors.white} />
                </View>
                {showWords ? (
                  <Marked word={w} q={q} plain={!isAnswer} style={[styles.word, SHORT_HEIGHT && styles.wordShort, { color: look.ink }]} hl={isAnswer ? styles.wordHlOk : styles.wordHl} underline={isAnswer} />
                ) : (
                  <Text maxFontSizeMultiplier={1.15} style={[styles.ear, SHORT_HEIGHT && styles.earShort, { color: look.ink }]}>
                    Word {i + 1}
                  </Text>
                )}
              </Pressable>
            );
          })}
        </View>

        <Text maxFontSizeMultiplier={1.2} style={[styles.note, SHORT_HEIGHT && styles.noteShort]} accessibilityLiveRegion="polite">
          {note || (done ? '' : 'Tap a card to hear it.')}
        </Text>
        {message ? <Overlay text={message} big={message === 'Level up!'} /> : null}
      </View>
    </Screen>
  );
}

// ------------------------------------------------------------------ pieces

/** A word with the part the question is about picked out (the rhyming end, the first letter or the last letter). */
function Marked({ word, q, plain, style, hl, underline }: { word: string; q: api.SoundQuestion; plain: boolean; style: object | object[]; hl: object; underline?: boolean }) {
  if (plain) {
    return (
      <Text maxFontSizeMultiplier={1.1} style={style}>
        {word}
      </Text>
    );
  }
  const [before, part, after] = markWord(word, q.kind, q.mark);
  return (
    <Text maxFontSizeMultiplier={1.1} style={style}>
      {before}
      <Text style={[hl, underline && styles.underline]}>{part}</Text>
      {after}
    </Text>
  );
}

const styles = StyleSheet.create({
  stage: { marginTop: space[8], backgroundColor: colors.sky, borderRadius: s(20), paddingVertical: space[12], paddingHorizontal: space[10], gap: space[12], overflow: 'hidden' },
  title: { fontFamily: fonts.displayMedium, fontSize: s(25), lineHeight: s(32), color: colors.ink, textAlign: 'center' },
  stageShort: { paddingVertical: space[8], gap: space[8] },
  titleShort: { fontSize: s(21), lineHeight: s(27) },
  pressed: { transform: [{ translateY: s(3) }] },

  spk: { width: s(44), height: s(44), borderRadius: s(22), backgroundColor: colors.orange, borderBottomWidth: s(3), borderBottomColor: colors.orangeDark, alignItems: 'center', justifyContent: 'center' },
  target: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space[10],
    minHeight: s(56),
    paddingVertical: space[6],
    paddingLeft: space[6],
    paddingRight: space[16],
    borderRadius: s(99),
    backgroundColor: colors.white,
    borderWidth: 2,
    borderColor: '#d3e3f4',
    borderBottomWidth: s(5),
    borderBottomColor: '#c3d8ee',
  },
  spkShort: { width: s(38), height: s(38), borderRadius: s(19) },
  targetShort: { minHeight: s(48), paddingVertical: space[4], gap: space[8] },
  targetWord: { fontFamily: fonts.displayMedium, fontSize: s(32), lineHeight: s(40), color: colors.blueDark },
  targetWordShort: { fontSize: s(28), lineHeight: s(34) },
  targetHl: { color: '#d2561f' },
  listen: { fontFamily: fonts.displayMedium, fontSize: s(22), lineHeight: s(30), color: colors.blueDark, textTransform: 'uppercase', letterSpacing: 1 },

  card: { minHeight: s(78), flexDirection: 'row', alignItems: 'center', gap: space[12], paddingVertical: space[8], paddingHorizontal: space[14], borderRadius: s(20), borderBottomWidth: s(6), borderWidth: 0 },
  cardShort: { minHeight: s(56), paddingVertical: space[4] },
  cardSel: { borderWidth: 3, borderColor: colors.blue },
  dim: { opacity: 0.42 },
  miss: { opacity: 0.45 },
  word: { fontFamily: fonts.displayMedium, fontSize: s(36), lineHeight: s(44) },
  wordShort: { fontSize: s(30), lineHeight: s(36) },
  earShort: { fontSize: s(20), lineHeight: s(26) },
  noteShort: { fontSize: s(16), lineHeight: s(21), minHeight: s(42) },
  wordHl: { color: '#d2561f' },
  wordHlOk: { color: '#0a6a3f' },
  underline: { textDecorationLine: 'underline' },
  ear: { fontFamily: fonts.displayMedium, fontSize: s(23), lineHeight: s(30), textTransform: 'uppercase', letterSpacing: 1 },

  note: { fontFamily: fonts.uiBold, fontSize: s(18), lineHeight: s(24), color: colors.blueDark, textAlign: 'center', minHeight: s(48) },
});
