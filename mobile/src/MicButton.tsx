import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Linking, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { AudioModule, RecordingPresets, setAudioModeAsync, useAudioRecorder, useAudioRecorderState } from 'expo-audio';
import { Btn, Icon, Notice, Small } from './ui';
import { colors, fonts } from './theme';

type Props = {
  /** Called with the saved file's address and how many seconds the child recorded. */
  onRecorded: (uri: string, seconds: number) => void;
  disabled?: boolean;
  /** What the label under the button says while it waits. */
  idleLabel?: string;
  /** A recording stops by itself after this long, like the website's. */
  maxSeconds?: number;
};

const SIZE = 92;
// On a short phone (about 640dp tall) the button gets smaller so the story keeps room above it.
const SIZE_SHORT = 76;

/**
 * The big microphone button, the main thing on a reading screen and placed where the thumb rests. One tap starts,
 * one tap stops. It asks for the microphone the first time and records an m4a (AAC) file, which the scoring service
 * reads directly. It sends nothing anywhere itself: the screen decides what to do with the file.
 */
export function MicButton({ onRecorded, disabled, idleLabel = 'Tap the mic and read the words', maxSeconds = 60 }: Props) {
  const { height } = useWindowDimensions();
  const px = height < 700 ? SIZE_SHORT : SIZE;
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const state = useAudioRecorderState(recorder, 250);
  const startedAt = useRef(0);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<{ text: string; settings: boolean } | null>(null);
  const pulse = useRef(new Animated.Value(0)).current;

  // A soft ring that breathes while the microphone is listening.
  useEffect(() => {
    if (!state.isRecording) {
      pulse.setValue(0);
      return;
    }
    const loop = Animated.loop(
      Animated.timing(pulse, { toValue: 1, duration: 1300, easing: Easing.out(Easing.quad), useNativeDriver: true }),
    );
    loop.start();
    return () => loop.stop();
  }, [state.isRecording, pulse]);

  async function start() {
    setProblem(null);
    setBusy(true);
    try {
      const permission = await AudioModule.requestRecordingPermissionsAsync();
      if (!permission.granted) {
        setProblem({ text: 'The microphone is blocked. Allow it for TaraBasa in the phone settings, then try again.', settings: !permission.canAskAgain });
        return;
      }
      await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
      await recorder.prepareToRecordAsync();
      recorder.record();
      startedAt.current = Date.now();
    } catch (e) {
      setProblem({ text: e instanceof Error ? e.message : 'The microphone could not start. Please try again.', settings: false });
    } finally {
      setBusy(false);
    }
  }

  async function stop() {
    setBusy(true);
    try {
      await recorder.stop();
      const uri = recorder.uri;
      if (!uri) throw new Error('No recording was saved. Please try again.');
      onRecorded(uri, (Date.now() - startedAt.current) / 1000);
    } catch (e) {
      setProblem({ text: e instanceof Error ? e.message : 'The recording could not be saved. Please try again.', settings: false });
    } finally {
      setBusy(false);
    }
  }

  // Stop by itself after the longest allowed time.
  useEffect(() => {
    if (!state.isRecording) return;
    const timer = setTimeout(stop, maxSeconds * 1000);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.isRecording]);

  const recording = state.isRecording;
  const seconds = Math.round((state.durationMillis ?? 0) / 1000);
  const clock = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
  const off = disabled || busy;

  return (
    <View style={styles.wrap}>
      <View style={[styles.stage, { width: px + 56, height: px + 20 }]}>
        {recording ? (
          <Animated.View
            pointerEvents="none"
            style={[
              styles.ring,
              { width: px, height: px, borderRadius: px / 2 },
              {
                opacity: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.45, 0] }),
                transform: [{ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 1.6] }) }],
              },
            ]}
          />
        ) : null}
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={recording ? 'Stop recording' : 'Start recording'}
          accessibilityHint={recording ? 'Tap when you finish reading' : 'Tap, then read the words out loud'}
          onPress={recording ? stop : start}
          disabled={off}
          style={({ pressed }) => [styles.button, { width: px, height: px, borderRadius: px / 2 }, recording && styles.buttonLive, pressed && styles.pressed, off && !recording && styles.off]}
        >
          <Icon name={recording ? 'check' : 'microphone'} size={px * 0.48} color={colors.white} />
        </Pressable>
      </View>

      <Text style={styles.label} accessibilityLiveRegion="polite">
        {recording ? `Listening  ${clock}` : idleLabel}
      </Text>
      {recording ? <Small>Tap the check mark when you finish</Small> : null}

      {problem ? (
        <View style={styles.problem}>
          <Notice text={problem.text} />
          {problem.settings ? <Btn label="Open settings" tone="ghost" compact onPress={() => Linking.openSettings()} /> : null}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { alignItems: 'center', gap: 6 },
  stage: { alignItems: 'center', justifyContent: 'center' },
  ring: { position: 'absolute', backgroundColor: colors.orange },
  button: {
    backgroundColor: colors.orange,
    borderBottomWidth: 7,
    borderBottomColor: colors.orangeDark,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonLive: { backgroundColor: colors.ok, borderBottomColor: '#1f6a41' },
  pressed: { transform: [{ translateY: 4 }], borderBottomWidth: 3 },
  off: { opacity: 0.55 },
  label: { fontFamily: fonts.uiBold, fontSize: 19, color: colors.ink, textAlign: 'center' },
  problem: { alignSelf: 'stretch', gap: 8, marginTop: 6 },
});
