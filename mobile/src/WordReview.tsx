import { StyleSheet, Text, View } from 'react-native';
import type { WordResult } from './api';
import { colors, fonts } from './theme';
import { Small } from './ui';
import { s } from './scale';

// How each word of the passage went. Never a grade and never a sad colour: a word that went wrong is shown in a
// calm way with a short note, so a child sees what to practise. Colour is never the only signal: every word that
// needs practice also carries a written note.

const LOOK: Record<WordResult['status'], { bg: string; fg: string; strike?: boolean }> = {
  correct: { bg: 'transparent', fg: colors.ok },
  repeated: { bg: colors.sky, fg: colors.blueDark },
  skip: { bg: colors.gold, fg: colors.warn, strike: true },
  sub: { bg: colors.purpleSoft, fg: colors.purple },
  mispronounced: { bg: colors.purpleSoft, fg: colors.purple },
  unsure: { bg: colors.unsureSoft, fg: colors.unsure },
};

function note(word: WordResult): string | null {
  switch (word.status) {
    case 'skip':
      return 'skipped';
    case 'sub':
    case 'mispronounced':
      return word.heard ? `heard "${word.heard}"` : 'said differently';
    case 'repeated':
      return 'said twice';
    case 'unsure':
      return 'not sure';
    default:
      return null;
  }
}

export function WordReview({ words, extra }: { words: WordResult[]; extra?: string[] }) {
  return (
    <View>
      <View style={styles.flow}>
        {words.map((word, i) => {
          const look = LOOK[word.status] ?? LOOK.correct;
          const n = note(word);
          return (
            <View key={`${word.text}-${i}`} style={[styles.chip, { backgroundColor: look.bg }]}>
              <Text
                maxFontSizeMultiplier={1.15}
                style={[styles.word, { color: look.fg }, look.strike && { textDecorationLine: 'line-through' }]}
              >
                {word.text}
              </Text>
              {n ? (
                <Text maxFontSizeMultiplier={1.15} style={[styles.note, { color: look.fg }]}>
                  {n}
                </Text>
              ) : null}
            </View>
          );
        })}
      </View>
      {extra && extra.length > 0 ? (
        <Small style={{ marginTop: s(10) }}>You also said: {extra.join(', ')}. That is not in this story, but great effort reading out loud.</Small>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  flow: { flexDirection: 'row', flexWrap: 'wrap', gap: s(6) },
  chip: { borderRadius: s(10), paddingHorizontal: s(9), paddingVertical: s(4), alignItems: 'center' },
  word: { fontFamily: fonts.read, fontSize: s(19), lineHeight: s(26) },
  note: { fontFamily: fonts.ui, fontSize: s(12.5), lineHeight: s(15) },
});
