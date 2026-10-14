import { useEffect, useRef, useState, type ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as Speech from 'expo-speech';
import * as api from './api';
import { Celebrate, Dots, GameDone, GameHeader, playSfx, prepareSounds } from './gameKit';
import { SayText } from './SayText';
import type { SayResult } from './SayWord';
import { s } from './scale';
import { Btn, Busy, Notice, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';

// The play loop Word Train and Sentence Stars share, the same rules as the website's read-aloud games. The child is shown something
// (a row of words or letters, a sentence), reads it out loud, and the same service that scores readings listens. A round is three
// things to read; two or more read right the first time make a clean round and climb a level. Each one gets two tries: "Tara is not
// sure" is never "wrong", the words that were heard are shown, and after two tries the game moves on anyway. Up to three rounds, or
// one round once on the top level. A microphone that is blocked or missing turns the game into "I read it!", so it can always be
// played. Free play: nothing a child says is graded or kept, and no points, streak or level change.

const MAX_ATTEMPTS = 3;
const TARGETS = 3;
const CLEAN_HITS = 2;
const MAX_TRIES = 2;

/** What a game gives the loop. Each target has `text`: the lowercase words the child is asked to read, separated by spaces. */
export type ReadGameConfig<T extends { text: string }> = {
  game: api.GameName;
  startLevel: number;
  /** "Train", "Sentence": the small label above the dots ("Train 1 of 3"). */
  unit: string;
  levelIcons: Record<number, string>;
  kind: (level: number) => api.ReadKind;
  /** Three targets for a round at this level. */
  makeTargets: (level: number) => T[];
  /** What the phone's voice says for "Hear it". */
  hear: (target: T) => string;
  micLabel: string;
  tapLabel: string;
  /** Draws the thing to read. `matched` says which of the words asked were heard (null before any try), `caught` is true as it is celebrated. */
  stage: (target: T, state: { matched: boolean[] | null; caught: boolean; index: number; total: number }) => ReactNode;
  hearLabel: string;
};

type Finished = { level: number; climbed: boolean; report: api.GameFinishBody };

export function ReadGame<T extends { text: string }>({
  config,
  firstName,
  onExit,
  demo,
}: {
  config: ReadGameConfig<T>;
  firstName: string;
  onExit: () => void;
  /** Developer gallery only: start part way through, so a screen can be looked at without a microphone. */
  demo?: { matched?: boolean[]; caught?: boolean; tries?: number; note?: string; level?: number; pos?: number };
}) {
  const [level, setLevel] = useState(demo?.level ?? config.startLevel);
  const [attempt, setAttempt] = useState(0);
  const [climbed, setClimbed] = useState(false);
  const [topCleared, setTopCleared] = useState(false);
  const [perfect, setPerfect] = useState(false);
  const [targets, setTargets] = useState<T[]>(() => config.makeTargets(demo?.level ?? config.startLevel));
  const [pos, setPos] = useState(demo?.pos ?? 0);
  const [hits, setHits] = useState(0);
  const [tries, setTries] = useState(demo?.tries ?? 0);
  const [matched, setMatched] = useState<boolean[] | null>(demo?.matched ?? null);
  const [caught, setCaught] = useState(demo?.caught ?? false);
  const [tapMode, setTapMode] = useState(false);
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState(demo?.note ?? '');
  const [message, setMessage] = useState<string | null>(null);
  const [finished, setFinished] = useState<Finished | null>(null);
  const active = useRef(false);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const later = (ms: number, work: () => void) => {
    timers.current.push(setTimeout(work, ms));
  };

  useEffect(() => {
    void prepareSounds();
    return () => {
      timers.current.forEach(clearTimeout);
      void Speech.stop();
    };
  }, []);

  const target = targets[pos];

  const hear = (t: T) => {
    void Speech.stop();
    Speech.speak(config.hear(t), { language: 'en-US', rate: 0.75 });
  };

  // The next thing is read aloud as it appears, once the child has tapped something (the first waits for "Hear it").
  useEffect(() => {
    if (active.current && !finished && target) hear(target);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pos, attempt]);

  function nextTarget(position: number, hitsNow: number) {
    if (position >= TARGETS) {
      roundDone(hitsNow);
      return;
    }
    setPos(position);
    setTries(0);
    setMatched(null);
    setCaught(false);
    setBusy(false);
    setNote('');
  }

  function finishTarget(firstTry: boolean) {
    setBusy(true);
    const hitsNow = firstTry ? hits + 1 : hits;
    if (firstTry) setHits(hitsNow);
    playSfx('correct', 0.55);
    setCaught(true);
    later(1000, () => nextTarget(pos + 1, hitsNow));
  }

  function sayResult(result: SayResult, heardWords: boolean[]) {
    active.current = true;
    if (result === 'unavailable') {
      // No microphone after all (blocked, missing, or the service cannot be reached): "I read it!".
      setTapMode(true);
      return;
    }
    if (result === 'heard') {
      setMatched(null);
      finishTarget(tries === 0);
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
      later(900, () => finishTarget(false));
    } else {
      setNote(some ? 'Tara heard some of it. Try once more!' : 'Tara is not sure. Try once more!');
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
    setMessage(up ? 'Level up!' : 'Round complete!');
    later(1700, () => {
      setMessage(null);
      setAttempt(attemptNow);
      setLevel(nextLevel);
      newRound(nextLevel);
    });
  }

  function newRound(forLevel: number) {
    setTargets(config.makeTargets(forLevel));
    setPos(0);
    setHits(0);
    setTries(0);
    setMatched(null);
    setCaught(false);
    setBusy(false);
    setNote('');
  }

  function playAgain() {
    // A new game tries the microphone again: "I read it!" was only for a microphone that did not work a moment ago.
    setTapMode(false);
    setFinished(null);
    setLevel(config.startLevel);
    setAttempt(0);
    setClimbed(false);
    setTopCleared(false);
    setPerfect(false);
    newRound(config.startLevel);
  }

  if (finished) {
    return <GameDone game={config.game} firstName={firstName} level={finished.level} climbed={finished.climbed} report={finished.report} onAgain={playAgain} onExit={onExit} />;
  }
  if (!target) return null;

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {tapMode ? (
            <>
              <Btn label="I read it!" onPress={() => { active.current = true; if (!busy) finishTarget(tries === 0); }} disabled={busy} />
              <Text maxFontSizeMultiplier={1.2} style={styles.note} accessibilityLiveRegion="polite">
                Read it out loud, then press the button.
              </Text>
            </>
          ) : busy ? (
            <Busy text={note || 'Nice!'} />
          ) : (
            <>
              <SayText key={`${attempt}-${pos}-${tries}`} text={target.text} kind={config.kind(level)} idleLabel={tries > 0 ? 'Tap the mic and try again' : config.micLabel} onResult={sayResult} />
              <Text maxFontSizeMultiplier={1.2} style={[styles.note, tries > 0 && { color: colors.blueDark }]} accessibilityLiveRegion="polite">
                {tries > 0 ? note : ''}
              </Text>
            </>
          )}
        </View>
      }
    >
      <GameHeader onBack={onExit} level={level} label={`Round ${attempt + 1} of ${MAX_ATTEMPTS}`} icons={config.levelIcons} />
      <View style={{ marginTop: space[4], gap: space[4] }}>
        <Small style={{ textAlign: 'center' }}>
          {config.unit} {Math.min(pos + 1, TARGETS)} of {TARGETS}
        </Small>
        <Dots done={pos} total={TARGETS} />
      </View>

      <View style={styles.stage}>
        {config.stage(target, { matched, caught, index: pos, total: TARGETS })}
        {message ? <Celebrate text={message} big={message === 'Level up!'} /> : null}
      </View>

      <View style={{ alignItems: 'center', marginTop: space[10] }}>
        <Btn
          label={config.hearLabel}
          icon="speaker-high"
          tone="ghost"
          compact
          onPress={() => {
            active.current = true;
            hear(target);
          }}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  footer: { gap: space[8], alignItems: 'center' },
  note: { fontFamily: fonts.uiBold, fontSize: s(16), lineHeight: s(21), color: colors.ink, textAlign: 'center', minHeight: s(21) },
  stage: { marginTop: space[10], backgroundColor: colors.sky, borderRadius: s(20), paddingVertical: space[12], paddingHorizontal: space[8], minHeight: s(210), justifyContent: 'center', overflow: 'hidden' },
});

/** Loads what a game needs from the server, with the same "try again" every game shows when the internet is down. */
export function GameLoader<D>({ load, busyText, onExit, children }: { load: () => Promise<D>; busyText: string; onExit: () => void; children: (data: D) => ReactNode }) {
  const [data, setData] = useState<D | null>(null);
  const [error, setError] = useState<string | null>(null);

  const run = async () => {
    setError(null);
    try {
      setData(await load());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  };

  useEffect(() => {
    void run();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (error) {
    return (
      <Screen anchor="content" footer={<Btn label="Back to Games" tone="ghost" onPress={onExit} />}>
        <View style={{ gap: space[12] }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={run} />
        </View>
      </Screen>
    );
  }
  if (!data) {
    return (
      <Screen scroll={false}>
        <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
          <Busy text={busyText} />
        </View>
      </Screen>
    );
  }
  return <>{children(data)}</>;
}
