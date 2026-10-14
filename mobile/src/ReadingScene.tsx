import { useEffect, useState, type ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as Speech from 'expo-speech';
import { Animation, IconBtn, Panel, Pill, Screen, Small } from './ui';
import { colors, fonts, READING_BASE, readingSteps, space } from './theme';
import { useSession } from './session';
import { COMPACT_HEIGHT, s, SHORT_HEIGHT } from './scale';

export type Letter = { upper: string; lower: string };
export type Passage = { kind: 'text'; text: string } | { kind: 'letters'; letters: Letter[] };

type Props = {
  /** A short line at the top, such as the story's title or "Passage 2". */
  eyebrow?: string;
  /** A small note under the top row, such as "Free practice. Nothing is saved." */
  pill?: string;
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
export function ReadingScene({ eyebrow, pill, steps, dots, direction, passage, below, footer, onBack, speakText }: Props) {
  const { fontStep, setFontStep } = useSession();
  const [speaking, setSpeaking] = useState(false);
  const [gridW, setGridW] = useState(0);

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
  const tile = letterTile(passage.kind === 'letters' ? passage.letters.length : 0, gridW);

  return (
    <Screen footer={footer}>
      <View style={styles.top}>
        {onBack ? <IconBtn icon="caret-left" label="Go back" onPress={onBack} /> : <View style={{ width: s(4) }} />}
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

      {pill ? (
        <View style={{ alignItems: 'center', marginTop: space[6] }}>
          <Pill icon="info" color={colors.blueDark} text={pill} />
        </View>
      ) : null}

      {steps ? (
        <View style={styles.steps}>
          {steps.labels.map((label, i) => (
            <View key={label} style={[styles.step, i === steps.active && styles.stepOn]} accessible accessibilityLabel={`${label}${i === steps.active ? ', now' : ''}`}>
              <Text maxFontSizeMultiplier={1.15} numberOfLines={1} adjustsFontSizeToFit style={[styles.stepText, i === steps.active && { color: colors.white }]}>
                {i + 1} {label}
              </Text>
            </View>
          ))}
        </View>
      ) : null}

      {/* Tara steps aside on a short phone, and on a story screen (it already has the step bar) on most phones: what the child has to read matters more than the decoration. */}
      {SHORT_HEIGHT || (COMPACT_HEIGHT && steps) ? (
        <View style={{ height: space[8] }} />
      ) : (
        <View style={[styles.owl, COMPACT_HEIGHT && styles.owlCompact]}>
          <Animation name="head" px={COMPACT_HEIGHT ? s(66) : s(92)} />
        </View>
      )}
      <Panel style={[styles.panel, (SHORT_HEIGHT || (COMPACT_HEIGHT && steps)) && styles.panelShort]}>
        <Text maxFontSizeMultiplier={1.2} style={[styles.direction, COMPACT_HEIGHT && styles.directionCompact, SHORT_HEIGHT && styles.directionShort]}>
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
          <View style={styles.letters} onLayout={(e) => setGridW(e.nativeEvent.layout.width)}>
            {passage.letters.map((letter, i) => (
              <View key={`${letter.upper}-${i}`} style={[styles.tile, tile.box]} accessible accessibilityLabel={`Letter ${letter.upper}`}>
                <Text maxFontSizeMultiplier={1.1} style={[styles.tileText, tile.upper]}>
                  {letter.upper}
                </Text>
                <Text maxFontSizeMultiplier={1.1} style={[styles.tileLower, tile.lower]}>
                  {letter.lower}
                </Text>
              </View>
            ))}
          </View>
        )}
      </Panel>

      {below ? <View style={{ marginTop: space[14], gap: space[12] }}>{below}</View> : null}
    </Screen>
  );
}

/**
 * The letter tiles share the panel's width, three to a row, so a row never wraps to two and pushes the last letters off
 * the screen. Before the panel has been measured they draw at their design size.
 */
function letterTile(count: number, gridW: number) {
  const cols = Math.max(1, Math.min(3, count));
  const gap = space[10];
  const width = gridW ? Math.min(SHORT_HEIGHT ? s(72) : s(92), Math.floor((gridW - gap * (cols - 1)) / cols)) : s(76);
  const f = Math.min(width / s(76), 1.15);
  return {
    box: { width, height: Math.round(width * (SHORT_HEIGHT ? 1.25 : 1.32)) },
    upper: { fontSize: Math.round(s(44) * f), lineHeight: Math.round(s(56) * f) },
    lower: { fontSize: Math.round(s(22) * f), lineHeight: Math.round(s(26) * f) },
  };
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
  top: { flexDirection: 'row', alignItems: 'center', gap: space[10], minHeight: s(52) },
  eyebrow: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.slate, textAlign: 'center' },
  dots: { flexDirection: 'row', gap: space[8], justifyContent: 'center', marginTop: space[2] },
  dot: { width: s(14), height: s(14), borderRadius: s(7), backgroundColor: colors.sky2 },
  dotDone: { backgroundColor: colors.ok },
  dotNow: { backgroundColor: colors.orange },

  steps: { flexDirection: 'row', gap: space[6], marginTop: space[8] },
  step: { flex: 1, minHeight: s(40), alignItems: 'center', justifyContent: 'center', borderRadius: s(12), backgroundColor: colors.white, borderWidth: 2, borderColor: colors.line, paddingHorizontal: space[2] },
  stepOn: { backgroundColor: colors.orange, borderColor: colors.orange },
  stepText: { fontFamily: fonts.uiBold, fontSize: s(12.5), color: colors.slate, textAlign: 'center' },

  // Tara's head overlaps the top edge of the panel, as on the website.
  owl: { alignItems: 'center', marginBottom: s(-30), zIndex: 1 },
  owlCompact: { marginBottom: s(-24) },
  panel: { paddingTop: space[32], gap: space[12] },
  panelShort: { paddingTop: space[14], gap: space[8] },
  direction: { fontFamily: fonts.uiBold, fontSize: s(20), lineHeight: s(26), color: colors.ink, textAlign: 'center' },
  directionCompact: { fontSize: s(18), lineHeight: s(24) },
  directionShort: { fontSize: s(17), lineHeight: s(22) },
  sizer: { flexDirection: 'row', alignItems: 'center', gap: space[8] },
  passage: { backgroundColor: colors.white, borderRadius: s(16), padding: space[14] },
  passageText: { fontFamily: fonts.read, color: colors.ink },
  letters: { flexDirection: 'row', flexWrap: 'wrap', gap: space[10], justifyContent: 'center' },
  tile: { width: s(76), height: s(100), borderRadius: s(18), backgroundColor: colors.white, borderBottomWidth: s(5), borderBottomColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  tileText: { fontFamily: fonts.display, fontSize: s(44), lineHeight: s(56), color: colors.orange },
  tileLower: { fontFamily: fonts.read, fontSize: s(22), lineHeight: s(26), color: colors.slate },
});
