import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import * as Speech from 'expo-speech';
import * as api from './api';
import { Celebrate, Dots, GameDone, GameHeader, pickWords, playSfx, prepareSounds, shuffle } from './gameKit';
import { SayWord, type SayResult } from './SayWord';
import { useSession } from './session';
import { Btn, Busy, Icon, IconBtn, Notice, Screen, Small } from './ui';
import { colors, fonts, size } from './theme';

// Balloon Pop, the same rules as the website: five balloons show words, three of them are marked in turn, and the child says the
// marked word to pop it. Each word gets two tries; "Tara is not sure" is never "wrong", and after two tries the balloon pops anyway.
// Popping two or more on the first try makes a clean round and climbs a level. Up to three rounds, or one round once on the top
// level. A microphone that is blocked or missing turns the game into tap-to-pop, so it can always be played. Free play: nothing a
// child says is graded or kept, and no points, streak or level change.

const MAX_ATTEMPTS = 3;
const TARGETS = 3;
const BALLOONS = 5;
const CLEAN_HITS = 2;
const MAX_TRIES = 2;
const COLORS = ['#e8505b', '#f5a623', '#2f7bd6', '#2e8b57', '#9b5de5'];
const LEVEL_ICON: Record<number, string> = { 1: 'balloon', 2: 'balloon', 3: 'trophy' };

type Finished = { level: number; climbed: boolean; report: api.GameFinishBody };

export function BalloonPop({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  const [data, setData] = useState<api.WordLevels | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await api.gameWords());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }, []);

  useEffect(() => {
    load();
    prepareSounds();
    return () => void Speech.stop();
  }, [load]);

  if (error) {
    return (
      <Screen footer={<Btn label="Back to Games" tone="ghost" onPress={onExit} />}>
        <View style={{ flex: 1, justifyContent: 'center', gap: 12 }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={load} />
        </View>
      </Screen>
    );
  }
  if (!data) {
    return (
      <Screen scroll={false}>
        <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
          <Busy text="Blowing up the balloons" />
        </View>
      </Screen>
    );
  }
  return <Play key={learner.id} data={data} firstName={learner.firstName} onExit={onExit} />;
}

function Play({ data, firstName, onExit }: { data: api.WordLevels; firstName: string; onExit: () => void }) {
  const [level, setLevel] = useState(data.startLevel);
  const [attempt, setAttempt] = useState(0);
  const [climbed, setClimbed] = useState(false);
  const [topCleared, setTopCleared] = useState(false);
  const [perfect, setPerfect] = useState(false);
  const [words, setWords] = useState<string[]>(() => pickWords(data.levels[String(data.startLevel)], BALLOONS));
  const [order, setOrder] = useState<number[]>(() => shuffle([0, 1, 2, 3, 4]).slice(0, TARGETS));
  const [pos, setPos] = useState(0);
  const [popped, setPopped] = useState<number[]>([]);
  const [hits, setHits] = useState(0);
  const [tries, setTries] = useState(0);
  const [tapMode, setTapMode] = useState(false);
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState('Tap the microphone and say the word.');
  const [message, setMessage] = useState<string | null>(null);
  const [finished, setFinished] = useState<Finished | null>(null);
  const active = useRef(false);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const later = (ms: number, work: () => void) => {
    timers.current.push(setTimeout(work, ms));
  };
  useEffect(() => () => timers.current.forEach(clearTimeout), []);

  const { height } = useWindowDimensions();
  const short = height < 700;
  const target = order[pos];
  const word = words[target] ?? '';

  const say = (w: string) => {
    Speech.stop();
    Speech.speak(w, { language: 'en-US', rate: 0.7 });
  };

  // The marked word is read aloud as each balloon is marked, once the child has tapped something (the first waits for Hear).
  useEffect(() => {
    if (active.current && !finished) say(word);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pos, attempt]);

  function pop(firstTry: boolean) {
    setBusy(true);
    const hitsNow = firstTry ? hits + 1 : hits;
    if (firstTry) setHits(hitsNow);
    playSfx('correct', 0.55);
    setPopped((p) => [...p, target]);
    later(650, () => {
      const nextPos = pos + 1;
      if (nextPos >= TARGETS) roundDone(hitsNow);
      else {
        setPos(nextPos);
        setTries(0);
        setBusy(false);
        setNote(tapMode ? 'Tap the marked balloon to pop it.' : 'Tap the microphone and say the word.');
      }
    });
  }

  function sayResult(result: SayResult) {
    active.current = true;
    if (result === 'unavailable') {
      // No microphone after all (blocked, missing, or the service cannot be reached): tap to pop.
      setTapMode(true);
      setNote('Tap the marked balloon to pop it.');
    } else if (result === 'heard') {
      pop(tries === 0);
    } else {
      const next = tries + 1;
      setTries(next);
      if (next >= MAX_TRIES) {
        setNote('Good try! On we go.');
        setBusy(true);
        later(700, () => pop(false));
      } else {
        setNote('Tara is not sure. Try once more!');
      }
    }
  }

  function roundDone(hitsNow: number) {
    const atCeiling = level >= 3;
    const top = topCleared || atCeiling;
    const perfectNow = perfect || hitsNow === TARGETS;
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
    setMessage(up ? 'Level up!' : 'Nice popping!');
    later(1700, () => {
      setMessage(null);
      setAttempt(attemptNow);
      setLevel(nextLevel);
      newRound(nextLevel);
    });
  }

  function newRound(forLevel: number) {
    setWords(pickWords(data.levels[String(forLevel)], BALLOONS));
    setOrder(shuffle([0, 1, 2, 3, 4]).slice(0, TARGETS));
    setPos(0);
    setPopped([]);
    setHits(0);
    setTries(0);
    setBusy(false);
    setNote(tapMode ? 'Tap the marked balloon to pop it.' : 'Tap the microphone and say the word.');
  }

  function playAgain() {
    // A new game tries the microphone again: tap-to-pop was only for a microphone that did not work a moment ago.
    setTapMode(false);
    setFinished(null);
    setLevel(data.startLevel);
    setAttempt(0);
    setClimbed(false);
    setTopCleared(false);
    setPerfect(false);
    newRound(data.startLevel);
    setNote('Tap the microphone and say the word.');
  }

  if (finished) {
    return <GameDone game="balloon-pop" firstName={firstName} level={finished.level} climbed={finished.climbed} report={finished.report} onAgain={playAgain} onExit={onExit} />;
  }

  return (
    <Screen
      footer={
        <View style={{ gap: 8, alignItems: 'center' }}>
          {tapMode ? (
            <Text maxFontSizeMultiplier={1.2} style={styles.note} accessibilityLiveRegion="polite">
              {note}
            </Text>
          ) : busy ? (
            <>
              <Busy text={note} />
            </>
          ) : (
            <>
              <SayWord key={`${attempt}-${pos}-${tries}`} word={word} idleLabel={tries > 0 ? 'Tap the mic and try again' : 'Tap the mic and say the word'} onResult={sayResult} />
              <Text maxFontSizeMultiplier={1.2} style={[styles.note, tries > 0 && { color: colors.blueDark }]} accessibilityLiveRegion="polite">
                {tries > 0 ? note : ''}
              </Text>
            </>
          )}
        </View>
      }
    >
      <GameHeader onBack={onExit} level={level} label={`Round ${attempt + 1} of ${MAX_ATTEMPTS}`} icons={LEVEL_ICON} />
      <View style={{ marginTop: 4, gap: 4 }}>
        {short ? null : <Small style={{ textAlign: 'center' }}>Balloon {Math.min(pos + 1, TARGETS)} of {TARGETS}</Small>}
        <Dots done={pos} total={TARGETS} />
      </View>

      <View style={styles.target} accessible={!short} accessibilityLabel={`Say this word: ${word}`}>
        <Text maxFontSizeMultiplier={1.15} style={styles.targetLabel}>
          {tapMode ? 'Pop this word' : 'Say this word'}
        </Text>
        {short ? (
          // A short phone (about 640dp tall): the word and the "hear it" button sit side by side, so the balloons keep their room.
          <View style={styles.targetShort}>
            <View style={styles.targetRow}>
              <Text maxFontSizeMultiplier={1.1} style={styles.targetWord}>
                {word}
              </Text>
            </View>
            <IconBtn icon="speaker-high" label="Hear the word" onPress={() => { active.current = true; say(word); }} />
          </View>
        ) : (
          <>
            <View style={styles.targetRow}>
              <Text maxFontSizeMultiplier={1.1} style={styles.targetWord}>
                {word}
              </Text>
            </View>
            <Btn label="Hear the word" icon="speaker-high" tone="ghost" compact onPress={() => { active.current = true; say(word); }} />
          </>
        )}
      </View>

      <View style={styles.sky}>
        <Balloons words={words} target={target} popped={popped} tapMode={tapMode && !busy} onTap={(i) => i === target && pop(tries === 0)} />
        {message ? <Celebrate text={message} big={message === 'Level up!'} /> : null}
      </View>
    </Screen>
  );
}

/** Five balloons, floating, in two rows. The marked one glows. A popped one swells and disappears. */
function Balloons({ words, target, popped, tapMode, onTap }: { words: string[]; target: number; popped: number[]; tapMode: boolean; onTap: (i: number) => void }) {
  const { width } = useWindowDimensions();
  const board = Math.min(size.maxWidth, width) - size.gutter * 2;
  // The sky has 8px of padding on each side and the balloons 12px between them, so they never touch the edge of the sky.
  const w = Math.min(112, (board - 16 - 24 - 8) / 3);
  const rows = [words.slice(0, 3).map((x, i) => [x, i] as const), words.slice(3).map((x, i) => [x, i + 3] as const)];
  return (
    <View style={{ gap: 4, alignItems: 'center' }}>
      {rows.map((row, r) => (
        <View key={r} style={{ flexDirection: 'row', justifyContent: 'center', gap: 12 }}>
          {row.map(([text, i]) => (
            <Balloon key={`${text}-${i}`} word={text} color={COLORS[i % COLORS.length]} w={w} isTarget={i === target} popped={popped.includes(i)} tappable={tapMode && i === target} delay={i * 260} onPress={() => onTap(i)} />
          ))}
        </View>
      ))}
    </View>
  );
}

/** A word's size on its balloon: as big as reads well, but never wider than the balloon (a long word like "monkey" is smaller than "cat"). */
function wordSize(word: string, balloonWidth: number) {
  const room = balloonWidth - 22; // the balloon's own padding and the marked balloon's border
  return Math.max(15, Math.min(balloonWidth * 0.24, room / (Math.max(word.length, 3) * 0.66)));
}

function Balloon({ word, color, w, isTarget, popped, tappable, delay, onPress }: { word: string; color: string; w: number; isTarget: boolean; popped: boolean; tappable: boolean; delay: number; onPress: () => void }) {
  const float = useRef(new Animated.Value(0)).current;
  const burst = useRef(new Animated.Value(0)).current;
  const h = w * 1.14;

  // A slow float, so the balloons feel alive. Each one starts at its own moment.
  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(float, { toValue: 1, duration: 1600, delay, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
        Animated.timing(float, { toValue: 0, duration: 1600, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [float, delay]);

  useEffect(() => {
    if (popped) Animated.timing(burst, { toValue: 1, duration: 260, useNativeDriver: true }).start();
  }, [popped, burst]);

  if (popped && burst) {
    // After it pops the space stays, so the other balloons do not jump around.
  }

  return (
    <Animated.View
      style={{
        width: w,
        height: h + 28,
        alignItems: 'center',
        opacity: burst.interpolate({ inputRange: [0, 1], outputRange: [1, 0] }),
        transform: [
          { translateY: float.interpolate({ inputRange: [0, 1], outputRange: [0, -7] }) },
          { scale: burst.interpolate({ inputRange: [0, 1], outputRange: [1, 1.5] }) },
        ],
      }}
    >
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${word}${isTarget ? ', the marked balloon' : ''}`}
        disabled={!tappable}
        onPress={onPress}
        style={[styles.balloon, { width: w, height: h, backgroundColor: color, borderRadius: w / 2 }, isTarget && !popped && styles.marked]}
      >
        <Text maxFontSizeMultiplier={1.1} numberOfLines={1} adjustsFontSizeToFit style={[styles.balloonText, { fontSize: wordSize(word, w) }]}>
          {word}
        </Text>
        <View style={styles.shine} />
      </Pressable>
      <View style={[styles.knot, { borderBottomColor: color }]} />
      <View style={styles.string} />
      {isTarget && !popped ? (
        <View style={styles.pointer}>
          <Icon name="arrow-up" size={18} color={colors.orangeDark} />
        </View>
      ) : null}
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  note: { fontFamily: fonts.uiBold, fontSize: 18, lineHeight: 24, color: colors.ink, textAlign: 'center', minHeight: 24 },
  target: { alignItems: 'center', gap: 6, marginTop: 10 },
  targetLabel: { fontFamily: fonts.ui, fontSize: 16, color: colors.slate },
  targetShort: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 12 },
  targetRow: { backgroundColor: colors.orangeSoft, borderRadius: 16, borderWidth: 2, borderColor: '#f3d6bb', paddingHorizontal: 22, paddingVertical: 4 },
  targetWord: { fontFamily: fonts.display, fontSize: 48, lineHeight: 62, color: colors.orange },
  sky: { marginTop: 10, backgroundColor: colors.sky, borderRadius: 22, paddingVertical: 10, paddingHorizontal: 8, minHeight: 270, justifyContent: 'center' },
  balloon: { alignItems: 'center', justifyContent: 'center', overflow: 'visible', borderBottomRightRadius: 40, borderBottomLeftRadius: 40 },
  marked: { borderWidth: 4, borderColor: colors.white, shadowColor: colors.orange, shadowOpacity: 0.9, shadowRadius: 12, elevation: 10 },
  balloonText: { fontFamily: fonts.read, color: colors.white, fontWeight: '700', textAlign: 'center', paddingHorizontal: 6 },
  shine: { position: 'absolute', top: '12%', left: '18%', width: '18%', height: '22%', borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.4)' },
  knot: { width: 0, height: 0, borderLeftWidth: 6, borderRightWidth: 6, borderBottomWidth: 9, borderLeftColor: 'transparent', borderRightColor: 'transparent', marginTop: -2 },
  string: { width: 2, height: 16, backgroundColor: 'rgba(23,50,77,0.35)' },
  pointer: { position: 'absolute', bottom: -2 },
});
