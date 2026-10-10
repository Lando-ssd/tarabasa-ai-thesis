import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { AudioModule, RecordingPresets, setAudioModeAsync, useAudioRecorder, useAudioRecorderState } from 'expo-audio';

type Props = {
  /** Text on the button while it is idle. */
  label: string;
  /** Called with the saved file's address and how long the child recorded. */
  onRecorded: (uri: string, seconds: number) => void;
  disabled?: boolean;
  /** A recording stops by itself after this long, like the website's. */
  maxSeconds?: number;
};

/**
 * One tap to start, one tap to stop. Asks for the microphone the first time, records an m4a (AAC) file,
 * which the scoring service reads directly, and hands the file back. It sends nothing anywhere itself.
 */
export function RecorderButton({ label, onRecorded, disabled, maxSeconds = 60 }: Props) {
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const state = useAudioRecorderState(recorder);
  const startedAt = useRef(0);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function start() {
    setError(null);
    setBusy(true);
    try {
      const permission = await AudioModule.requestRecordingPermissionsAsync();
      if (!permission.granted) {
        setError('The microphone is blocked. Allow it in the phone settings for this app, then try again.');
        return;
      }
      await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
      await recorder.prepareToRecordAsync();
      recorder.record();
      startedAt.current = Date.now();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'The microphone could not start.');
    } finally {
      setBusy(false);
    }
  }

  async function stop() {
    setBusy(true);
    try {
      await recorder.stop();
      const uri = recorder.uri;
      if (!uri) throw new Error('No recording was saved.');
      onRecorded(uri, (Date.now() - startedAt.current) / 1000);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'The recording could not be saved.');
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

  const seconds = Math.round((state.durationMillis ?? 0) / 1000);

  return (
    <View>
      <Pressable
        style={[styles.button, state.isRecording && styles.recording, (disabled || busy) && styles.disabled]}
        onPress={state.isRecording ? stop : start}
        disabled={disabled || busy}
      >
        {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.text}>{state.isRecording ? `Stop (${seconds}s)` : label}</Text>}
      </Pressable>
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  button: { backgroundColor: '#dd7014', borderRadius: 14, padding: 14, alignItems: 'center' },
  recording: { backgroundColor: '#b3261e' },
  disabled: { opacity: 0.55 },
  text: { color: '#fff', fontWeight: '700', fontSize: 16 },
  error: { color: '#b3261e', fontWeight: '600', marginTop: 6 },
});
