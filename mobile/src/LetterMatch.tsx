import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import * as Speech from 'expo-speech';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as api from './api';
import { Celebrate, Dots, GameDone, GameHeader, gameMemory, playSfx, prepareSounds, shuffle } from './gameKit';
import { useSession } from './session';
import { Busy, Icon, Notice, Btn, Screen, Small } from './ui';
import { colors, fonts, size } from './theme';

// Letter Match, the same rules as the website: find a capital letter and its small twin. Three levels (6 pairs, 10 pairs, then the
// whole alphabet in three sets). A round is one attempt at a level; finishing it with three or fewer wrong pairs climbs a level,
// and a game is up to three rounds, or one round once a child is on the top level. Free play: no points, no streak, no level.

const MAX_ATTEMPTS = 3;
const WRONG_THRESHOLD = 3;
const MEMORY_KEY = (learnerId: number) => `letter-match:${learnerId}`;

// Letters are read by their NAME ("ay", "bee"): a bare letter would be read as a word, like "a" or "I".
const LETTER_NAMES: Record<string, string> = {
  A: 'ay', B: 'bee', C: 'see', D: 'dee', E: 'ee', F: 'eff', G: 'jee', H: 'aitch', I: 'eye', J: 'jay', K: 'kay', L: 'el', M: 'em',
  N: 'en', O: 'oh', P: 'pee', Q: 'cue', R: 'are', S: 'ess', T: 'tee', U: 'you', V: 'vee', W: 'double you', X: 'ex', Y: 'why', Z: 'zee',
};

type Card = { pairId: string; display: string; matched: boolean };
type Snapshot = {
  level: number;
  attempt: number;
  climbed: boolean;
  topCleared: boolean;
  perfect: boolean;
  setIndex: number;
  wrong: number;
  cards: Card[];
};
type Finished = { level: number; climbed: boolean; report: api.GameFinishBody };

function deck(letters: string[]): Card[] {
  return shuffle(letters.flatMap((l) => [{ pairId: l, display: l, matched: false }, { pairId: l, display: l.toLowerCase(), matched: false }]));
}

/**
 * The number of columns that makes the cards biggest on this screen. Only counts the cards divide into evenly are tried, so no
 * row is left ragged, and the winner is the one whose SMALLER side is largest (so cards stay friendly squares, not strips).
 */
function bestGrid(count: number, boardW: number, space: number, gap: number) {
  let best = { columns: 4, w: 56, h: 56, score: 0 };
  for (const columns of [2, 3, 4, 5, 6]) {
    if (count % columns !== 0) continue;
    const rows = count / columns;
    const w = (boardW - gap * (columns - 1)) / columns;
    const h = Math.min(w * 1.3, (space - gap * (rows - 1)) / rows);
    const score = Math.min(w, h);
    if (score > best.score) best = { columns, w, h, score };
  }
  return { columns: best.columns, w: best.w, h: Math.max(56, best.h) };
}

export function LetterMatch({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  const [info, setInfo] = useState<api.GamesInfo | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setInfo(await api.games());
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
  if (!info) {
    return (
      <Screen scroll={false}>
        <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
          <Busy text="Getting the cards ready" />
        </View>
      </Screen>
    );
  }
  return <Board key={learner.id} info={info} firstName={learner.firstName} memoryKey={MEMORY_KEY(learner.id)} onExit={onExit} />;
}

function Board({ info, firstName, memoryKey, onExit }: { info: api.GamesInfo; firstName: string; memoryKey: string; onExit: () => void }) {
  const levels = info.letterLevels;
  const saved = useRef(gameMemory.get<Snapshot>(memoryKey));
  const start = saved.current;

  const [level, setLevel] = useState(start?.level ?? info.startLevel);
  const [attempt, setAttempt] = useState(start?.attempt ?? 0);
  const [climbed, setClimbed] = useState(start?.climbed ?? false);
  const [topCleared, setTopCleared] = useState(start?.topCleared ?? false);
  const [perfect, setPerfect] = useState(start?.perfect ?? false);
  const [setIndex, setSetIndex] = useState(start?.setIndex ?? 0);
  const [wrong, setWrong] = useState(start?.wrong ?? 0);
  const [cards, setCards] = useState<Card[]>(start?.cards ?? deck(levels[String(info.startLevel)][0]));
  const [flipped, setFlipped] = useState<number[]>([]);
  const [locked, setLocked] = useState(false);
  const [wrongPair, setWrongPair] = useState<number[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [finished, setFinished] = useState<Finished | null>(null);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  const sets = levels[String(level)];
  const later = (ms: number, work: () => void) => {
    timers.current.push(setTimeout(work, ms));
  };
  useEffect(() => () => timers.current.forEach(clearTimeout), []);

  // Keep the board so a child who leaves and comes back finds it as they left it.
  const snapshot = (over: Partial<Snapshot> = {}) =>
    gameMemory.set(memoryKey, { level, attempt, climbed, topCleared, perfect, setIndex, wrong, cards, ...over } satisfies Snapshot);

  function newSet(forLevel: number, index: number, attemptNow: number, over: Partial<Snapshot> = {}) {
    const next = deck(levels[String(forLevel)][index]);
    setCards(next);
    setFlipped([]);
    setLocked(false);
    setSetIndex(index);
    snapshot({ level: forLevel, attempt: attemptNow, setIndex: index, cards: next, ...over });
  }

  function tap(i: number) {
    if (locked || cards[i].matched || flipped.includes(i)) return;
    const nowFlipped = [...flipped, i];
    setFlipped(nowFlipped);
    Speech.stop();
    const name = LETTER_NAMES[cards[i].display.toUpperCase()];
    if (name) Speech.speak(name, { language: 'en-US', rate: 0.8 });
    if (nowFlipped.length < 2) return;

    setLocked(true);
    const [a, b] = nowFlipped;
    if (cards[a].pairId === cards[b].pairId) {
      const next = cards.map((c, n) => (n === a || n === b ? { ...c, matched: true } : c));
      setCards(next);
      setFlipped([]);
      setLocked(false);
      playSfx('correct', 0.55);
      if (next.every((c) => c.matched)) later(400, () => setDone(next));
      else snapshot({ cards: next });
    } else {
      playSfx('wrong', 0.3);
      const wrongNow = wrong + 1;
      setWrong(wrongNow);
      setWrongPair([a, b]);
      later(700, () => {
        setFlipped([]);
        setWrongPair([]);
        setLocked(false);
        snapshot({ wrong: wrongNow });
      });
    }
  }

  // A set is finished: the next set of this level, or the round is over.
  function setDone(finishedCards: Card[]) {
    void finishedCards;
    const nextIndex = setIndex + 1;
    if (nextIndex < sets.length) {
      playSfx('level', 0.45);
      setMessage('Nice matching!');
      later(1100, () => {
        setMessage(null);
        newSet(level, nextIndex, attempt);
      });
      return;
    }
    roundDone();
  }

  function roundDone() {
    const atCeiling = level >= 3;
    const top = topCleared || atCeiling;
    const perfectNow = perfect || wrong === 0;
    const up = wrong <= WRONG_THRESHOLD && level < 3;
    const climbedNow = climbed || up;
    const attemptNow = attempt + 1;
    const nextLevel = up ? level + 1 : level;
    setTopCleared(top);
    setPerfect(perfectNow);
    setClimbed(climbedNow);

    if (attemptNow >= MAX_ATTEMPTS || atCeiling) {
      gameMemory.clear(memoryKey);
      setFinished({
        level: nextLevel,
        climbed: climbedNow,
        report: { level_reached: nextLevel, top_level_cleared: top, had_perfect_round: perfectNow, rounds: Math.max(1, Math.min(3, attemptNow)) },
      });
      return;
    }

    setAttempt(attemptNow);
    setLevel(nextLevel);
    setWrong(0);
    if (up) {
      playSfx('level', 0.6);
      setMessage('Level up!');
      later(1400, () => {
        setMessage(null);
        newSet(nextLevel, 0, attemptNow, { wrong: 0, climbed: climbedNow, topCleared: top, perfect: perfectNow });
      });
    } else {
      newSet(nextLevel, 0, attemptNow, { wrong: 0, climbed: climbedNow, topCleared: top, perfect: perfectNow });
    }
  }

  function playAgain() {
    gameMemory.clear(memoryKey);
    setFinished(null);
    setLevel(info.startLevel);
    setAttempt(0);
    setClimbed(false);
    setTopCleared(false);
    setPerfect(false);
    setWrong(0);
    newSet(info.startLevel, 0, 0, { level: info.startLevel, wrong: 0, climbed: false, topCleared: false, perfect: false });
  }

  if (finished) {
    return (
      <GameDone
        game="letter-match"
        firstName={firstName}
        level={finished.level}
        climbed={finished.climbed}
        report={finished.report}
        onAgain={playAgain}
        onExit={onExit}
      />
    );
  }

  return (
    <Screen>
      <GameHeader onBack={onExit} level={level} label={`Round ${attempt + 1} of ${MAX_ATTEMPTS}`} />
      {sets.length > 1 ? (
        <View style={{ marginTop: 4, gap: 4 }}>
          <Small style={{ textAlign: 'center' }}>Set {setIndex + 1} of {sets.length}</Small>
          <Dots done={setIndex} total={sets.length} />
        </View>
      ) : null}
      <Small style={{ textAlign: 'center', marginTop: 8 }}>Tap two cards. Find a big letter and its small twin.</Small>

      <View style={styles.boardWrap}>
        <Grid cards={cards} flipped={flipped} wrongPair={wrongPair} locked={locked} onTap={tap} hasHeader={sets.length > 1} />
        {message ? <Celebrate text={message} big={message === 'Level up!'} /> : null}
      </View>
    </Screen>
  );
}

/** The cards, sized to fill the space left on this phone: as big as fits, never smaller than a comfortable tap. */
function Grid({
  cards,
  flipped,
  wrongPair,
  locked,
  onTap,
  hasHeader,
}: {
  cards: Card[];
  flipped: number[];
  wrongPair: number[];
  locked: boolean;
  onTap: (i: number) => void;
  hasHeader: boolean;
}) {
  const { width, height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const gap = 10;
  const boardW = Math.min(size.maxWidth, width) - size.gutter * 2;
  const space = height - insets.top - insets.bottom - (hasHeader ? 215 : 175);
  const { w: cardW, h: cardH } = bestGrid(cards.length, boardW, space, gap);
  const font = Math.min(cardW, cardH) * 0.52;

  return (
    <View style={[styles.grid, { gap }]}>
      {cards.map((card, i) => (
        <LetterCard
          key={`${card.display}-${i}`}
          card={card}
          faceUp={card.matched || flipped.includes(i)}
          wrong={wrongPair.includes(i)}
          disabled={locked || card.matched}
          w={cardW}
          h={cardH}
          font={font}
          onPress={() => onTap(i)}
        />
      ))}
    </View>
  );
}

function LetterCard({
  card,
  faceUp,
  wrong,
  disabled,
  w,
  h,
  font,
  onPress,
}: {
  card: Card;
  faceUp: boolean;
  wrong: boolean;
  disabled: boolean;
  w: number;
  h: number;
  font: number;
  onPress: () => void;
}) {
  const shake = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (!wrong) return;
    Animated.sequence([
      Animated.timing(shake, { toValue: 1, duration: 70, useNativeDriver: true }),
      Animated.timing(shake, { toValue: -1, duration: 70, useNativeDriver: true }),
      Animated.timing(shake, { toValue: 1, duration: 70, useNativeDriver: true }),
      Animated.timing(shake, { toValue: 0, duration: 70, useNativeDriver: true }),
    ]).start();
  }, [wrong, shake]);

  const label = card.matched ? `${card.display}, matched` : faceUp ? card.display : 'Face down card';
  return (
    <Animated.View style={{ transform: [{ translateX: shake.interpolate({ inputRange: [-1, 1], outputRange: [-6, 6] }) }] }}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={label}
        onPress={onPress}
        disabled={disabled}
        style={({ pressed }) => [
          styles.card,
          { width: w, height: h },
          card.matched ? styles.matched : faceUp ? styles.up : styles.down,
          wrong && styles.wrong,
          pressed && { transform: [{ translateY: 2 }] },
        ]}
      >
        {faceUp ? (
          <Text maxFontSizeMultiplier={1.1} style={[styles.letter, { fontSize: font, lineHeight: font * 1.25 }, card.matched && { color: colors.ok }]}>
            {card.display}
          </Text>
        ) : (
          <Icon name="star" size={Math.min(w, h) * 0.46} color="rgba(255,255,255,0.92)" />
        )}
        {card.matched ? (
          <View style={styles.tick}>
            <Icon name="check" size={14} color={colors.white} />
          </View>
        ) : null}
      </Pressable>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  boardWrap: { marginTop: 12, alignItems: 'center' },
  grid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center' },
  card: { borderRadius: 16, borderBottomWidth: 5, alignItems: 'center', justifyContent: 'center' },
  down: { backgroundColor: colors.blue, borderBottomColor: colors.blueDark },
  up: { backgroundColor: colors.white, borderWidth: 2, borderColor: colors.sky2, borderBottomColor: colors.sky2 },
  matched: { backgroundColor: colors.mint, borderBottomColor: '#b9dfc9' },
  wrong: { borderColor: '#ff8a80', borderWidth: 2, backgroundColor: colors.badSoft },
  letter: { fontFamily: fonts.display, color: colors.orange },
  tick: { position: 'absolute', top: 5, right: 5, width: 20, height: 20, borderRadius: 10, backgroundColor: colors.ok, alignItems: 'center', justifyContent: 'center' },
});
