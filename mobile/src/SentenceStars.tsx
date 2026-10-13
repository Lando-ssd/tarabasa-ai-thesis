import { useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { shuffle } from './gameKit';
import { GameLoader, ReadGame, type ReadGameConfig } from './ReadGame';
import { s } from './scale';
import { useSession } from './session';
import { Icon } from './ui';
import { colors, fonts } from './theme';

// Sentence Stars, the same rules as the website: a sentence to read out loud. Reading it catches a star; three sentences make a
// round. The sentences come first from the ones that contain a word the child has been missing or that practise their main kind
// of mistake, then from the level's own sentences (the server decides: see PracticeGameService::sentenceLevels). Every game is a
// read-aloud game.

type Sentence = { sentence: string; tokens: string[]; text: string };

/** The word a token asks for: lowercase, with its punctuation taken off. The same cleaning the server does. */
const clean = (token: string) => token.toLowerCase().replace(/[^a-z']/g, '');

export function SentenceStars({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  return (
    <GameLoader load={api.gameSentences} busyText="Finding the stars" onExit={onExit}>
      {(data) => <ReadGame key={learner.id} config={configFor(data)} firstName={learner.firstName} onExit={onExit} />}
    </GameLoader>
  );
}

export function configFor(data: api.SentenceLevels): ReadGameConfig<Sentence> {
  return {
    game: 'sentence-stars',
    startLevel: data.startLevel,
    unit: 'Sentence',
    levelIcons: { 1: 'plant', 2: 'leaf', 3: 'tree' },
    kind: () => 'sentence',
    micLabel: 'Tap the mic and read the sentence',
    tapLabel: 'I read it!',
    hearLabel: 'Hear the sentence',
    // Three different sentences: the ones that suit this child first, then the level's.
    makeTargets: (level) => {
      const lv = data.levels[String(level)];
      const preferred = shuffle(lv.preferred);
      const rest = shuffle(lv.fallback.filter((x) => !preferred.includes(x)));
      return preferred
        .concat(rest)
        .slice(0, 3)
        .map((sentence) => {
          const tokens = sentence.split(/\s+/).filter((tok) => clean(tok));
          return { sentence, tokens, text: tokens.map(clean).join(' ') };
        });
    },
    hear: (t) => t.sentence,
    stage: (t, { matched, caught, index }) => <SentenceView sentence={t} matched={matched} caught={caught} index={index} key={t.text} />,
  };
}

function SentenceView({ sentence, matched, caught, index }: { sentence: Sentence; matched: boolean[] | null; caught: boolean; index: number }) {
  const pop = useRef(new Animated.Value(0)).current;

  // The star for this sentence pops as soon as it is read.
  useEffect(() => {
    if (caught) Animated.spring(pop, { toValue: 1, friction: 4, tension: 120, useNativeDriver: true }).start();
    else pop.setValue(0);
  }, [caught, pop]);

  const long = sentence.tokens.length > 9;
  return (
    <View style={{ gap: s(12) }} accessible accessibilityLabel={`Read the sentence: ${sentence.sentence}`}>
      <View style={styles.stars}>
        {[0, 1, 2].map((i) => {
          const lit = i < index || (i === index && caught);
          const popping = i === index && caught;
          return (
            <Animated.View key={i} style={popping ? { transform: [{ scale: pop.interpolate({ inputRange: [0, 1], outputRange: [0.3, 1] }) }] } : undefined}>
              <Icon name="star" size={s(40)} color={lit ? '#f6b93b' : '#c9d3e6'} />
            </Animated.View>
          );
        })}
      </View>
      <View style={[styles.panel, caught && styles.panelCaught]}>
        <View style={styles.words}>
          {sentence.tokens.map((token, i) => {
            const heard = caught || !!matched?.[i];
            return (
              <View key={`${token}-${i}`} style={[styles.chip, heard && styles.chipHeard]}>
                <Text maxFontSizeMultiplier={1.15} style={[styles.word, long && styles.wordLong, heard && { color: colors.ok }]}>
                  {token}
                </Text>
              </View>
            );
          })}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  stars: { flexDirection: 'row', justifyContent: 'center', gap: s(12) },
  panel: { backgroundColor: colors.white, borderRadius: s(18), borderWidth: 2, borderColor: '#d6def0', borderBottomWidth: s(5), borderBottomColor: '#c9d3e6', paddingVertical: s(14), paddingHorizontal: s(10) },
  panelCaught: { borderColor: '#f6b93b', borderBottomColor: '#e0a21d' },
  words: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', columnGap: s(4), rowGap: s(4) },
  chip: { paddingHorizontal: s(7), borderRadius: s(9) },
  chipHeard: { backgroundColor: colors.mint },
  word: { fontFamily: fonts.read, fontWeight: '500', fontSize: s(22), lineHeight: s(34), color: colors.ink },
  wordLong: { fontSize: s(18), lineHeight: s(28) },
});
