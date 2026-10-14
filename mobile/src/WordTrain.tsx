import { useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import * as api from './api';
import { LETTER_NAMES, shuffle } from './gameKit';
import { GameLoader, ReadGame, type ReadGameConfig } from './ReadGame';
import { s } from './scale';
import { useSession } from './session';
import { Icon } from './ui';
import { colors, fonts, space } from './theme';

// Word Train, the same rules as the website: a train of cars, each one a word (or, for a child still on the letters step, a
// letter). The child reads the whole train out loud and it rolls away. The words come first from the child's own missed words,
// then from the ones that practise their kind of mistake, then from the level's list (the server decides: see
// App\Services\PracticeGameService::trainLevels). Every game is a read-aloud game.

type Train = { words: string[]; text: string };

const CAR_COLORS = ['#ef5b4a', '#2f8de0', '#2bb89c', '#f2b134', '#9b6bd6'];
const HEARD = '#2e9e6b';

export function WordTrain({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  return (
    <GameLoader load={api.gameTrain} busyText="Getting the train ready" onExit={onExit}>
      {(data) => <ReadGame key={learner.id} config={configFor(data)} firstName={learner.firstName} onExit={onExit} />}
    </GameLoader>
  );
}

/** Everything a level can offer, in the order it should be used: the child's own words, the pattern words, then the rest, each shuffled. */
function pool(pool: api.WordPool): string[] {
  const own = shuffle(pool.struggling);
  const fav = shuffle(pool.favoured.filter((w) => !own.includes(w)));
  const rest = shuffle(pool.fallback.filter((w) => !own.includes(w) && !fav.includes(w)));
  return own.concat(fav, rest);
}

export function configFor(data: api.TrainLevels): ReadGameConfig<Train> {
  const letters = data.mode === 'letters';
  return {
    game: 'word-train',
    startLevel: data.startLevel,
    unit: 'Train',
    levelIcons: { 1: 'plant', 2: 'leaf', 3: 'tree' },
    kind: () => (letters ? 'letters' : 'row'),
    micLabel: letters ? 'Tap the mic and say the letters' : 'Tap the mic and read the words',
    tapLabel: 'I read it!',
    hearLabel: letters ? 'Hear the letters' : 'Hear the words',
    // Three trains for a round, taken from one pool without repeating, so the child's own words are spread across them.
    makeTargets: (level) => {
      const n = data.cars[String(level)] ?? 3;
      const p = pool(data.levels[String(level)]);
      return [0, 1, 2].map((k) => {
        const words = shuffle(Array.from({ length: n }, (_, j) => p[(k * n + j) % p.length]));
        return { words, text: words.join(' ') };
      });
    },
    hear: (t) => (letters ? t.words.map((l) => LETTER_NAMES[l] ?? l).join(', ') : t.words.join(', ')),
    stage: (t, { matched, caught }) => <TrainView train={t} letters={letters} matched={matched} caught={caught} key={t.text} />,
  };
}

function TrainView({ train, letters, matched, caught }: { train: Train; letters: boolean; matched: boolean[] | null; caught: boolean }) {
  const roll = useRef(new Animated.Value(0)).current;
  const { width } = useWindowDimensions();

  // When the whole train was read it rolls away to the right.
  useEffect(() => {
    if (caught) Animated.timing(roll, { toValue: 1, duration: 900, easing: Easing.in(Easing.quad), useNativeDriver: true }).start();
    else roll.setValue(0);
  }, [caught, roll]);

  return (
    <Animated.View style={{ transform: [{ translateX: roll.interpolate({ inputRange: [0, 1], outputRange: [0, width] }) }] }} accessible accessibilityLabel={`Read the train: ${train.words.join(', ')}`}>
      <View style={styles.row}>
        <View style={styles.engine} accessible={false}>
          <Icon name="train" size={s(26)} color={colors.white} />
          <Wheels />
        </View>
        {train.words.map((word, i) => {
          const heard = caught || !!matched?.[i];
          return (
            <View key={`${word}-${i}`} style={[styles.car, letters && styles.carLetter, { backgroundColor: heard ? HEARD : CAR_COLORS[i % CAR_COLORS.length] }, heard && { transform: [{ translateY: -s(4) }] }]}>
              <View style={styles.coupler} />
              <Text maxFontSizeMultiplier={1.15} style={[styles.word, letters && styles.letter]}>
                {letters ? word.toUpperCase() : word}
              </Text>
              {heard ? (
                <View style={styles.tick} accessible={false}>
                  <Icon name="check" size={s(14)} color={HEARD} />
                </View>
              ) : null}
              <Wheels />
            </View>
          );
        })}
      </View>
    </Animated.View>
  );
}

function Wheels() {
  return (
    <View style={styles.wheels} pointerEvents="none">
      <View style={styles.wheel} />
      <View style={styles.wheel} />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', alignItems: 'flex-end', columnGap: space[10], rowGap: space[24], paddingBottom: space[10], paddingTop: space[8] },
  engine: { width: s(46), height: s(46), borderRadius: s(13), backgroundColor: '#5b6b7a', alignItems: 'center', justifyContent: 'center', borderBottomWidth: s(6), borderBottomColor: 'rgba(0,0,0,0.2)' },
  car: { minWidth: s(46), paddingHorizontal: space[8], paddingTop: space[8], paddingBottom: space[12], borderRadius: s(14), borderBottomWidth: s(6), borderBottomColor: 'rgba(0,0,0,0.18)', alignItems: 'center', justifyContent: 'center' },
  carLetter: { minWidth: s(38), paddingHorizontal: space[6] },
  coupler: { position: 'absolute', left: -s(14), top: '45%', width: s(14), height: s(5), borderRadius: s(3), backgroundColor: '#8aa0b4' },
  word: { fontFamily: fonts.read, fontWeight: '700', fontSize: s(18), lineHeight: s(24), color: colors.white },
  letter: { fontFamily: fonts.display, fontWeight: '400', fontSize: s(28), lineHeight: s(36) },
  tick: { position: 'absolute', top: -s(10), right: -s(8), width: s(22), height: s(22), borderRadius: s(11), backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  wheels: { position: 'absolute', left: '14%', right: '14%', bottom: -s(10), flexDirection: 'row', justifyContent: 'space-between' },
  wheel: { width: s(11), height: s(11), borderRadius: s(6), backgroundColor: '#3b4a58' },
});
