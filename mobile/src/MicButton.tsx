import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Linking, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { AudioModule, RecordingPresets, setAudioModeAsync, useAudioRecorder, useAudioRecorderState } from 'expo-audio';
import { HINTS, judgeRecording, METER_MS, PAUSE_MIN_VOICE, PAUSE_MS, VOICE_DB } from './recording';
import { discardRecording } from './recordingFiles';
import { Btn, Icon, Notice, Small } from './ui';
import { colors, fonts } from './theme';
import { s } from './scale';

/** What the phone learned about the sound while it recorded. A level is null when the phone gave no level at all. */
export type RecordingInfo = { peakDb: number | null; voiceSamples: number };

type Props = {
  /** Called with the saved file's address, how many seconds the child recorded, and how loud it was. */
  onRecorded: (uri: string, seconds: number, info: RecordingInfo) => void;
  disabled?: boolean;
  /** What the label under the button says while it waits. */
  idleLabel?: string;
  /** A recording stops by itself after this long, like the website's. */
  maxSeconds?: number;
  /**
   * Check the recording on the phone before handing it over. A recording that is too short, or in which the microphone
   * heard no voice at all, is NOT handed over: the child is told kindly and the button is ready again straight away.
   * The warm-up turns this off because it judges the sound itself.
   */
  checkSound?: boolean;
  /** Receives the microphone level (dB) about ten times a second while recording, to draw a live voice meter. */
  onMeter?: (db: number) => void;
  /** Replaces the "we did not hear anything" note, for a place where the recording stops by itself and there is no check mark to tap. */
  silentHint?: string;
  /** Stop by itself once this many readings of a voice were heard (the warm-up: "say the word" and it is done). */
  autoStopAfterVoice?: number;
  /** For a row of words or a sentence: stop by itself once the child has spoken and then paused, as well as on a tap of the check mark. */
  stopOnPause?: boolean;
};

const SIZE = s(92);
// On a short phone (about 640dp tall) the button gets smaller so the story keeps room above it.
const SIZE_SHORT = s(76);

/**
 * The big microphone button, the main thing on a reading screen and placed where the thumb rests. One tap starts,
 * one tap stops. It asks for the microphone the first time and records an m4a (AAC) file, which the scoring service
 * reads directly. It sends nothing anywhere itself: the screen decides what to do with the file.
 */
export function MicButton({
  onRecorded,
  disabled,
  idleLabel = 'Tap the mic and read the words',
  maxSeconds = 60,
  checkSound = true,
  onMeter,
  autoStopAfterVoice,
  silentHint,
  stopOnPause,
}: Props) {
  const { height } = useWindowDimensions();
  const px = height < 700 ? SIZE_SHORT : SIZE;
  const recorder = useAudioRecorder({ ...RecordingPresets.HIGH_QUALITY, isMeteringEnabled: true });
  const state = useAudioRecorderState(recorder, 250);
  const startedAt = useRef(0);
  const peak = useRef<number | null>(null);
  const voice = useRef(0);
  const lastVoiceAt = useRef(0);
  const stopping = useRef(false);
  const meterTimer = useRef<ReturnType<typeof setInterval> | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<{ text: string; settings: boolean } | null>(null);
  const [hint, setHint] = useState<string | null>(null);
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

  function stopMeter() {
    if (meterTimer.current) {
      clearInterval(meterTimer.current);
      meterTimer.current = null;
    }
  }
  useEffect(() => stopMeter, []);

  // Read the level while recording. The loudest moment of the whole recording decides whether anything was said.
  function startMeter() {
    stopMeter();
    peak.current = null;
    voice.current = 0;
    lastVoiceAt.current = 0;
    meterTimer.current = setInterval(() => {
      let level: number | undefined;
      try {
        level = recorder.getStatus().metering;
      } catch {
        return; // the recorder is busy changing state; the next reading will do
      }
      if (typeof level !== 'number' || !Number.isFinite(level)) return;
      peak.current = peak.current === null ? level : Math.max(peak.current, level);
      const now = Date.now();
      if (level > VOICE_DB) {
        voice.current += 1;
        lastVoiceAt.current = now;
      }
      onMeter?.(level);
      if (autoStopAfterVoice && voice.current >= autoStopAfterVoice) void stopRef.current();
      // A row or a sentence: the child spoke, then stopped. That is "I am done", so nobody has to find the check mark.
      if (stopOnPause && voice.current >= PAUSE_MIN_VOICE && now - lastVoiceAt.current > PAUSE_MS && now - startedAt.current > 1800) void stopRef.current();
    }, METER_MS);
  }

  async function start() {
    setProblem(null);
    setHint(null);
    setBusy(true);
    try {
      const permission = await AudioModule.requestRecordingPermissionsAsync();
      if (!permission.granted) {
        setProblem({ text: 'The microphone is blocked. Allow it for TaraBasa in the phone settings, then try again.', settings: !permission.canAskAgain });
        return;
      }
      await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
      await recorder.prepareToRecordAsync();
      stopping.current = false;
      recorder.record();
      startedAt.current = Date.now();
      startMeter();
    } catch (e) {
      setProblem({ text: e instanceof Error ? e.message : 'The microphone could not start. Please try again.', settings: false });
    } finally {
      setBusy(false);
    }
  }

  async function stop() {
    if (stopping.current) return; // a double tap, or the timer and a tap together, must stop only once
    stopping.current = true;
    stopMeter();
    setBusy(true);
    try {
      await recorder.stop();
      const uri = recorder.uri;
      if (!uri) throw new Error('No recording was saved. Please try again.');
      const seconds = (Date.now() - startedAt.current) / 1000;
      const judged = checkSound ? judgeRecording(seconds, peak.current) : 'ok';
      if (judged !== 'ok') {
        // Nothing is sent. Tell the child kindly and let them try again at once. The unused file is removed.
        discardRecording(uri);
        setHint(judged === 'silent' && silentHint ? silentHint : HINTS[judged]);
        return;
      }
      onRecorded(uri, seconds, { peakDb: peak.current, voiceSamples: voice.current });
    } catch (e) {
      setProblem({ text: e instanceof Error ? e.message : 'The recording could not be saved. Please try again.', settings: false });
    } finally {
      setBusy(false);
    }
  }

  // The timers call the newest stop (a stale one would use old props).
  const stopRef = useRef(stop);
  stopRef.current = stop;

  // Stop by itself after the longest allowed time.
  useEffect(() => {
    if (!state.isRecording) return;
    const timer = setTimeout(() => void stopRef.current(), maxSeconds * 1000);
    return () => clearTimeout(timer);
  }, [state.isRecording, maxSeconds]);

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
      {recording ? <Small>{autoStopAfterVoice ? 'Say it now' : stopOnPause ? 'Read it now. Pause when you finish.' : 'Tap the check mark when you finish'}</Small> : null}

      {hint && !recording ? (
        <View style={styles.problem}>
          <Notice tone="info" text={hint} />
        </View>
      ) : null}

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
  wrap: { alignItems: 'center', gap: s(6) },
  stage: { alignItems: 'center', justifyContent: 'center' },
  ring: { position: 'absolute', backgroundColor: colors.orange },
  button: {
    backgroundColor: colors.orange,
    borderBottomWidth: s(7),
    borderBottomColor: colors.orangeDark,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonLive: { backgroundColor: colors.ok, borderBottomColor: '#1f6a41' },
  pressed: { transform: [{ translateY: 4 }], borderBottomWidth: s(3) },
  off: { opacity: 0.55 },
  label: { fontFamily: fonts.uiBold, fontSize: s(19), color: colors.ink, textAlign: 'center' },
  problem: { alignSelf: 'stretch', gap: s(8), marginTop: s(6) },
});
