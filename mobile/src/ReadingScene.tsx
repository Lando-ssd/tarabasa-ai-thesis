import { useEffect, useState, type ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as Speech from 'expo-speech';
import { Animation, Icon, IconBtn, Panel, Screen, Small } from './ui';
import { colors, fonts, READING_BASE, readingSteps } from './theme';
import { useSession } from './session';

export type Letter = { upper: string; lower: string };
export type Passage = { kind: 'text'; text: string } | { kind: 'letters'; letters: Letter[] };

type Props = {
  /** A short line at the top, such as the story's title or "Passage 2". */
  eyebrow?: string;
  /** The three stages of a story (Listen, Your turn, Read for real), with the active one marked. */
  steps?: { labels: string[]; active: number };
  /** Progress through the first reading check. */
  dots?: { done: number; total: number };
  /** What to do, in plain words. */
  direction: string;
  passage: Passage;
  /** Shown under the passage: feedback after a practice try, a note, the quiz. */
  below?: ReactNode;
  /** The main action, pinned to the bottom of the screen where the thumb is. */
  footer: ReactNode;
  onBack?: () => void;
  /** When set, a speaker button reads this out loud with the phone's own voice. */
  speakText?: string;
};

/**
 * The screen a child reads on. Tara peeks over a blue panel that holds the direction, the text size control and the
 * passage in a large reading font. The microphone (the footer) is the biggest thing on the screen.
 */
export function ReadingScene({ eyebrow, steps, dots, direction, passage, below, footer, onBack, speakText }: Props) {
  const { fontStep, setFontStep } = useSession();
  const [speaking, setSpeaking] = useState(false);

  // Stop talking when the screen goes away.
  useEffect(() => () => void Speech.stop(), []);

  function toggleSpeech() {
    if (!speakText) return;
    if (speaking) {
      Speech.stop();
      setSpeaking(false);
      return;
    }
    setSpeaking(true);
    Speech.speak(speakText, {
      language: 'en-US',
      rate: 0.85,
      onDone: () => setSpeaking(false),
      onStopped: () => setSpeaking(false),
      onError: () => setSpeaking(false),
    });
  }

  const step = readingSteps[Math.min(Math.max(fontStep, 1), 5) - 1];
  const fontSize = Math.round(READING_BASE * step.scale);

  return (
    <Screen footer={footer}>
      <View style={styles.top}>
        {onBack ? <IconBtn icon="caret-left" label="Go back" onPress={onBack} /> : <View style={{ width: 4 }} />}
        <View style={{ flex: 1 }}>
          {eyebrow ? (
            <Text numberOfLines={1} maxFontSizeMultiplier={1.2} style={styles.eyebrow}>
              {eyebrow}
            </Text>
          ) : null}
          {dots ? <Dots done={dots.done} total={dots.total} /> : null}
        </View>
        {speakText ? <IconBtn icon="speaker-high" label={speaking ? 'Stop reading aloud' : 'Read it to me'} onPress={toggleSpeech} tone={speaking ? 'orange' : 'ghost'} /> : null}
      </View>

      {steps ? (
        <View style={styles.steps}>
          {steps.labels.map((label, i) => (
            <View key={label} style={[styles.step, i === steps.active && styles.stepOn]} accessible accessibilityLabel={`${label}${i === steps.active ? ', now' : ''}`}>
              <Text maxFontSizeMultiplier={1.15} style={[styles.stepText, i === steps.active && { color: colors.white }]}>
                {i + 1} {label}
              </Text>
            </View>
          ))}
        </View>
      ) : null}

      <View style={styles.owl}>
        <Animation name="head" px={92} />
      </View>
      <Panel style={styles.panel}>
        <Text maxFontSizeMultiplier={1.2} style={styles.direction}>
          {direction}
        </Text>

        {passage.kind === 'text' ? (
          <>
            <View style={styles.sizer}>
              <Small style={{ flex: 1 }}>Text size: {step.label}</Small>
              <IconBtn icon="minus" label="Smaller text" onPress={() => setFontStep(fontStep - 1)} disabled={fontStep <= 1} />
              <IconBtn icon="plus" label="Larger text" onPress={() => setFontStep(fontStep + 1)} disabled={fontStep >= 5} />
            </View>
            <View style={styles.passage}>
              <Text maxFontSizeMultiplier={1.1} style={[styles.passageText, { fontSize, lineHeight: Math.round(fontSize * 1.55) }]}>
                {passage.text}
              </Text>
            </View>
          </>
        ) : (
          <View style={styles.letters}>
            {passage.letters.map((letter, i) => (
              <View key={`${letter.upper}-${i}`} style={styles.tile} accessible accessibilityLabel={`Letter ${letter.upper}`}>
                <Text maxFontSizeMultiplier={1.1} style={styles.tileText}>
                  {letter.upper}
                </Text>
                <Text maxFontSizeMultiplier={1.1} style={styles.tileLower}>
                  {letter.lower}
                </Text>
              </View>
            ))}
          </View>
        )}
      </Panel>

      {below ? <View style={{ marginTop: 14, gap: 12 }}>{below}</View> : null}
    </Screen>
  );
}

function Dots({ done, total }: { done: number; total: number }) {
  return (
    <View style={styles.dots} accessible accessibilityLabel={`Passage ${done + 1} of up to ${total}`}>
      {Array.from({ length: total }, (_, i) => (
        <View key={i} style={[styles.dot, i < done && styles.dotDone, i === done && styles.dotNow]} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 52 },
  eyebrow: { fontFamily: fonts.uiBold, fontSize: 17, color: colors.slate, textAlign: 'center' },
  dots: { flexDirection: 'row', gap: 8, justifyContent: 'center', marginTop: 2 },
  dot: { width: 14, height: 14, borderRadius: 7, backgroundColor: colors.sky2 },
  dotDone: { backgroundColor: colors.ok },
  dotNow: { backgroundColor: colors.orange },

  steps: { flexDirection: 'row', gap: 6, marginTop: 8 },
  step: { flex: 1, minHeight: 40, alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: colors.white, borderWidth: 2, borderColor: colors.line, paddingHorizontal: 2 },
  stepOn: { backgroundColor: colors.orange, borderColor: colors.orange },
  stepText: { fontFamily: fonts.uiBold, fontSize: 14, color: colors.slate, textAlign: 'center' },

  // Tara's head overlaps the top edge of the panel, as on the website.
  owl: { alignItems: 'center', marginBottom: -30, zIndex: 1 },
  panel: { paddingTop: 36, gap: 12 },
  direction: { fontFamily: fonts.uiBold, fontSize: 20, lineHeight: 26, color: colors.ink, textAlign: 'center' },
  sizer: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  passage: { backgroundColor: colors.white, borderRadius: 16, padding: 14 },
  passageText: { fontFamily: fonts.read, color: colors.ink },
  letters: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, justifyContent: 'center' },
  tile: { width: 76, height: 100, borderRadius: 18, backgroundColor: colors.white, borderBottomWidth: 5, borderBottomColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  tileText: { fontFamily: fonts.display, fontSize: 44, lineHeight: 56, color: colors.orange },
  tileLower: { fontFamily: fonts.read, fontSize: 22, lineHeight: 26, color: colors.slate },
});
