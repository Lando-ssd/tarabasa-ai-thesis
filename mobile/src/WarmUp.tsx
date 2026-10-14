import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as Speech from 'expo-speech';
import { LevelBar } from './LevelBar';
import { MicButton } from './MicButton';
import { levelShare, VOICE_DB, WARM_MAX_SECONDS, WARM_VOICE_SAMPLES } from './recording';
import { discardRecording } from './recordingFiles';
import { Animation, Btn, Icon, IconBtn, Panel, Pill, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';
import { COMPACT_HEIGHT, s, SHORT_HEIGHT } from './scale';

type Outcome = 'none' | 'heard' | 'quiet';

/**
 * The warm-up before the very first reading item: say one easy word, so the child hears "I heard you!" and knows the
 * microphone works before anything counts. The level is measured on the phone and NOTHING is recorded for scoring or sent
 * anywhere. Whatever happens, there is a way on: a failed try can be repeated, and "Skip this part" is always there, so
 * a broken microphone never traps a child (the first reading check then explains itself with a kind message).
 */
export function WarmUp({
  letters,
  onDone,
  demo,
}: {
  /** True when the first item is the letters check: the warm-up then uses a letter instead of a word. */
  letters: boolean;
  onDone: () => void;
  /** Developer gallery only: show a result without a microphone. */
  demo?: Outcome;
}) {
  const [outcome, setOutcome] = useState<Outcome>(demo ?? 'none');
  const [level, setLevel] = useState(demo === 'heard' ? 0.8 : 0);

  const direction = letters ? 'Say this letter out loud.' : 'Say this word out loud.';
  const shown = letters ? 'A a' : 'sun';
  const ask = letters ? 'Tap the mic, then say the letter.' : 'Tap the mic, then say the word.';
  const heard = outcome === 'heard';
  // The "I did not hear anything" note needs room, so while it shows (and on a short phone) the screen tightens up and Tara steps aside.
  const tight = SHORT_HEIGHT || COMPACT_HEIGHT || outcome === 'quiet';

  return (
    <Screen
      footer={
        <View style={{ gap: space[10] }}>
          {heard ? (
            <>
              <View style={styles.result} accessibilityLiveRegion="polite">
                <Icon name="check-circle" size={s(26)} color={colors.ok} />
                <Text maxFontSizeMultiplier={1.2} style={[styles.resultText, { color: colors.ok }]}>
                  I heard you! Your microphone works.
                </Text>
              </View>
              <Btn label="Start Reading" onPress={onDone} />
            </>
          ) : (
            <>
              <MicButton
                idleLabel={ask}
                checkSound={false}
                maxSeconds={WARM_MAX_SECONDS}
                autoStopAfterVoice={WARM_VOICE_SAMPLES}
                onMeter={(db) => setLevel(levelShare(db))}
                onRecorded={(uri, _seconds, info) => {
                  discardRecording(uri); // the warm-up only listens; the file is never needed
                  setLevel(0);
                  setOutcome(info.voiceSamples >= WARM_VOICE_SAMPLES || (info.peakDb ?? -160) > VOICE_DB + 5 ? 'heard' : 'quiet');
                }}
              />
              {outcome === 'quiet' ? (
                <View style={styles.result} accessibilityLiveRegion="polite">
                  <Icon name="info" size={s(24)} color={colors.blueDark} />
                  <Text maxFontSizeMultiplier={1.2} style={[styles.resultText, { color: colors.ink }]}>
                    I did not hear anything. Check that the microphone is on, then tap it and try again.
                  </Text>
                </View>
              ) : null}
              <Btn label="Skip This Part" tone="ghost" compact onPress={onDone} />
            </>
          )}
        </View>
      }
    >
      <View style={[styles.top, tight && styles.topShort]}>
        <View style={[{ flex: 1, alignItems: 'center', gap: space[6] }, tight && { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap' }]}>
          <Text maxFontSizeMultiplier={1.2} style={styles.eyebrow}>
            Warm-up
          </Text>
          <Pill icon="info" color={colors.blueDark} text="This part does not count" />
        </View>
      </View>

      {outcome === 'quiet' ? null : (
        <View style={[styles.owl, (SHORT_HEIGHT || COMPACT_HEIGHT) && styles.owlShort]}>
          <Animation name="head" px={SHORT_HEIGHT ? s(60) : COMPACT_HEIGHT ? s(70) : s(92)} />
        </View>
      )}
      <Panel style={[styles.panel, tight && styles.panelShort]}>
        <View style={styles.directionRow}>
          <Text maxFontSizeMultiplier={1.2} style={styles.direction}>
            {direction}
          </Text>
          <IconBtn icon="speaker-high" label="Hear the directions" onPress={() => Speech.speak(direction, { language: 'en-US', rate: 0.85 })} />
        </View>
        <View style={[styles.wordCard, tight && styles.wordCardShort]} accessible accessibilityLabel={letters ? 'The letter A' : 'The word sun'}>
          <Text maxFontSizeMultiplier={1.1} style={[styles.word, tight && styles.wordShort]}>
            {shown}
          </Text>
        </View>
        <LevelBar share={level} />
        {/* After a quiet try the note under the mic already explains what to do, so this hint steps aside to keep the screen on one page. */}
        {outcome === 'quiet' ? null : <Small style={{ textAlign: 'center' }}>{heard ? 'Nice and clear.' : 'The bars move when the phone hears you.'}</Small>}
      </Panel>
    </Screen>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', minHeight: s(52) },
  eyebrow: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.slate },
  owl: { alignItems: 'center', marginBottom: s(-30), zIndex: 1 },
  panel: { paddingTop: space[32], gap: space[14] },
  topShort: { minHeight: s(36) },
  owlShort: { marginBottom: s(-22), marginTop: 0 },
  panelShort: { paddingTop: space[24], gap: space[8] },
  wordCardShort: { paddingVertical: space[8] },
  wordShort: { fontSize: s(44), lineHeight: s(54) },
  directionRow: { flexDirection: 'row', alignItems: 'center', gap: space[10] },
  direction: { flex: 1, fontFamily: fonts.uiBold, fontSize: s(20), lineHeight: s(26), color: colors.ink },
  wordCard: { backgroundColor: colors.white, borderRadius: s(16), paddingVertical: space[16], alignItems: 'center' },
  word: { fontFamily: fonts.read, fontSize: s(56), lineHeight: s(72), color: colors.ink },
  result: { flexDirection: 'row', alignItems: 'center', gap: space[10], justifyContent: 'center' },
  resultText: { flexShrink: 1, fontFamily: fonts.uiBold, fontSize: s(18), lineHeight: s(24), textAlign: 'center' },
});
