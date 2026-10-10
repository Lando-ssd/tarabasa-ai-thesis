import { useState, type ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useAudioPlayer } from 'expo-audio';
import * as Speech from 'expo-speech';
import LottieView from 'lottie-react-native';
import * as api from './api';
import { RecorderButton } from './RecorderButton';

// A plain developer check screen, NOT the real app design. Each card proves one thing a real phone has to do
// for TaraBasa: reach the server, use the microphone, score a recording, scan a card, speak and animate.
// A tester taps through the cards and reports which ones worked. The real screens come after the design is approved.

export function PhoneCheck({ needsFirstCheck }: { needsFirstCheck: boolean }) {
  return (
    <View style={styles.stack}>
      <ServerCheck />
      <MicCheck />
      {needsFirstCheck ? <FirstCheck /> : <ReadCheck />}
      <CameraCheck />
      <VoiceAndAnimationCheck />
    </View>
  );
}

// ---------------------------------------------------------------- small pieces

function Card({ number, title, children }: { number: string; title: string; children: ReactNode }) {
  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>
        {number}. {title}
      </Text>
      {children}
    </View>
  );
}

function Btn({ label, onPress, disabled }: { label: string; onPress: () => void; disabled?: boolean }) {
  return (
    <Pressable style={[styles.btn, disabled && styles.disabled]} onPress={onPress} disabled={disabled}>
      <Text style={styles.btnText}>{label}</Text>
    </Pressable>
  );
}

function Out({ text, bad }: { text: string | null; bad?: boolean }) {
  if (!text) return null;
  return (
    <Text selectable style={[styles.out, bad && styles.bad]}>
      {text}
    </Text>
  );
}

function Working({ text }: { text: string }) {
  return (
    <View style={styles.working}>
      <ActivityIndicator />
      <Text style={styles.small}>{text}</Text>
    </View>
  );
}

function message(e: unknown) {
  return e instanceof Error ? e.message : 'Something went wrong.';
}

/** A short plain summary of the word by word feedback: "cat, dog, pig (sub, heard hand)". */
function breakdownLine(words: any[] | undefined) {
  if (!Array.isArray(words)) return '';
  return words
    .map((w) => `${w.text ?? '?'}${w.status && w.status !== 'correct' ? ` (${w.status}${w.heard ? `, heard ${w.heard}` : ''})` : ''}`)
    .join(', ');
}

// ---------------------------------------------------------------- 1. the server

function ServerCheck() {
  const [out, setOut] = useState<string | null>(null);
  const [bad, setBad] = useState(false);
  const [busy, setBusy] = useState(false);

  async function run() {
    setBusy(true);
    setOut(null);
    setBad(false);
    const started = Date.now();
    try {
      await api.warm();
      setOut(`The server answered in ${((Date.now() - started) / 1000).toFixed(1)} seconds.`);
    } catch (e) {
      setBad(true);
      setOut(message(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card number="1" title="Reach the server">
      <Text style={styles.small}>Wakes the scoring service. The first time after a quiet period it can take up to a minute.</Text>
      <Btn label="Wake and time the server" onPress={run} disabled={busy} />
      {busy ? <Working text="Waiting for the server..." /> : null}
      <Out text={out} bad={bad} />
    </Card>
  );
}

// ---------------------------------------------------------------- 2. the microphone

function MicCheck() {
  const player = useAudioPlayer(null);
  const [clip, setClip] = useState<{ uri: string; seconds: number } | null>(null);

  function play() {
    if (!clip) return;
    player.replace(clip.uri);
    player.play();
  }

  return (
    <Card number="2" title="Microphone">
      <Text style={styles.small}>Say a few words, stop, then play it back. Allow the microphone when the phone asks.</Text>
      <RecorderButton label="Record, then tap again to stop" onRecorded={(uri, seconds) => setClip({ uri, seconds })} maxSeconds={20} />
      {clip ? <Out text={`Saved ${clip.seconds.toFixed(1)} seconds.\n${clip.uri.split('/').pop()}`} /> : null}
      <Btn label="Play it back" onPress={play} disabled={!clip} />
    </Card>
  );
}

// ---------------------------------------------------------------- 3. reading a real activity

function ReadCheck() {
  const [options, setOptions] = useState<api.ActivityOption[] | null>(null);
  const [detail, setDetail] = useState<api.ActivityDetail | null>(null);
  const [picked, setPicked] = useState<Record<number, string>>({});
  const [busy, setBusy] = useState<string | null>(null);
  const [out, setOut] = useState<string | null>(null);
  const [bad, setBad] = useState(false);

  async function guarded(label: string, work: () => Promise<string>) {
    setBusy(label);
    setOut(null);
    setBad(false);
    try {
      setOut(await work());
    } catch (e) {
      setBad(true);
      setOut(message(e));
    } finally {
      setBusy(null);
    }
  }

  const loadList = () =>
    guarded('Loading...', async () => {
      const result = await api.activities();
      setOptions(result.options);
      return result.options.length ? `${result.options.length} activities found. Tap one.` : 'No activity has been given to this child yet.';
    });

  const open = (id: number) =>
    guarded('Opening...', async () => {
      const result = await api.activityDetail(id);
      setDetail(result);
      setPicked({});
      return `Opened "${result.title}". Practice tries left: ${result.practice.triesLeft}. Starts at: ${result.practice.startAt}.`;
    });

  const practice = (uri: string) =>
    guarded('Scoring the practice try. The server may be waking up...', async () => {
      const r = await api.practiceTry(detail!.id, uri);
      if (r.status === 'no_tries_left') return 'Both practice tries are used. Read for real now.';
      if (r.status === 'unclear') return `Not heard clearly. Tries left: ${r.triesLeft}.`;
      return `Practice (not saved): ${Math.round(r.accuracy)}% accuracy. Tries left: ${r.triesLeft}.\n${breakdownLine(r.wordBreakdown)}`;
    });

  const real = (uri: string) =>
    guarded('Scoring your reading. The server may be waking up...', async () => {
      const answers = detail!.quizQuestions.map((_, i) => picked[i] ?? '');
      const r = await api.realReading(detail!.id, uri, detail!.quizQuestions.length ? answers : undefined);
      if (r.status === 'unclear') return r.final ? 'Not heard clearly three times. Ask a grown up for help.' : 'Not heard clearly. Try again.';
      const lines = [
        `Scored: ${Math.round(r.accuracy)}% accuracy, ${r.wcpm ?? '?'} words a minute, +${r.pointsEarned} points.`,
        `Level ${r.levelBefore} to ${r.levelAfter}. Days in a row: ${r.learner?.dayStreak}.`,
        r.progress ? `Progress: ${JSON.stringify(r.progress)}` : '',
        r.comprehension ? `Quiz: ${r.comprehension.correctCount} of ${r.comprehension.totalCount} right.` : '',
        `New badges: ${(r.newBadges ?? []).length}.`,
        breakdownLine(r.wordBreakdown),
      ];
      return lines.filter(Boolean).join('\n');
    });

  return (
    <Card number="3" title="Read an activity and get scored">
      <Btn label="Load my activities" onPress={loadList} disabled={!!busy} />
      {options?.map((o) => (
        <Pressable key={o.id} style={[styles.option, detail?.id === o.id && styles.optionOn]} onPress={() => open(o.id)} disabled={!!busy}>
          <Text style={styles.optionText}>{o.title}</Text>
          <Text style={styles.small}>
            {o.source}
            {o.practice ? ` | ${o.practice}` : ''}
          </Text>
        </Pressable>
      ))}

      {detail ? (
        <View style={styles.stack}>
          <Text style={styles.passage}>{detail.passageText}</Text>
          <Text style={styles.small}>{detail.instructions}</Text>

          <Text style={styles.cardSub}>Practice try (free, nothing is saved)</Text>
          <RecorderButton label="Record a practice try" onRecorded={(uri) => practice(uri)} disabled={!!busy} />

          {detail.quizQuestions.map((q, i) => (
            <View key={i} style={styles.stack}>
              <Text style={styles.cardSub}>{q.question}</Text>
              {q.choices.map((c) => (
                <Pressable key={c} style={[styles.option, picked[i] === c && styles.optionOn]} onPress={() => setPicked({ ...picked, [i]: c })}>
                  <Text style={styles.optionText}>{c}</Text>
                </Pressable>
              ))}
            </View>
          ))}

          <Text style={styles.cardSub}>Read for real (counts, scored by the server)</Text>
          <RecorderButton label="Record the real reading" onRecorded={(uri) => real(uri)} disabled={!!busy} />
        </View>
      ) : null}

      {busy ? <Working text={busy} /> : null}
      <Out text={out} bad={bad} />
    </Card>
  );
}

// ---------------------------------------------------------------- 3b. the first reading check (a child with no check yet)

function FirstCheck() {
  const [item, setItem] = useState<api.DiagnosticPassage | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [out, setOut] = useState<string | null>(null);
  const [bad, setBad] = useState(false);

  async function guarded(label: string, work: () => Promise<string>) {
    setBusy(label);
    setOut(null);
    setBad(false);
    try {
      setOut(await work());
    } catch (e) {
      setBad(true);
      setOut(message(e));
    } finally {
      setBusy(null);
    }
  }

  const getItem = () =>
    guarded('Getting the next item...', async () => {
      const result = await api.firstCheckPassage();
      setItem(result);
      return `Item ${result.passageNumber} of up to ${result.maxPassages}.`;
    });

  const record = (uri: string) =>
    guarded('Scoring. The server may be waking up...', async () => {
      const r = await api.firstCheckRecord(uri);
      if (r.status === 'finished') {
        setItem(null);
        return `First check finished. Level: ${r.finalLevel}. ${r.resultLabel ?? ''} New badges: ${(r.newBadges ?? []).length}. Sign out and in again to see Home.`;
      }
      if (r.status === 'unclear') return r.final ? 'Not heard clearly three times. Ask a grown up for help.' : 'Not heard clearly. Try again.';
      return `Saved. The check goes on: ${JSON.stringify(r).slice(0, 300)}\nTap "Get the next item".`;
    });

  return (
    <Card number="3" title="First reading check">
      <Text style={styles.small}>This child has not done the first check yet. It places the child on the reading path.</Text>
      <Btn label={item ? 'Get the next item' : 'Get my first item'} onPress={getItem} disabled={!!busy} />
      {item ? (
        <View style={styles.stack}>
          <Text style={styles.small}>{item.activity.prompt}</Text>
          <Text style={styles.passage}>{item.activity.kind === 'letters' ? (item.activity.letters ?? []).join('   ') : item.activity.passageText}</Text>
          <RecorderButton label="Record the answer" onRecorded={(uri) => record(uri)} disabled={!!busy} />
        </View>
      ) : null}
      {busy ? <Working text={busy} /> : null}
      <Out text={out} bad={bad} />
    </Card>
  );
}

// ---------------------------------------------------------------- 4. the camera (a learner card)

function CameraCheck() {
  const [permission, requestPermission] = useCameraPermissions();
  const [open, setOpen] = useState(false);
  const [found, setFound] = useState<string | null>(null);

  async function openCamera() {
    if (!permission?.granted) {
      const asked = await requestPermission();
      if (!asked.granted) return;
    }
    setFound(null);
    setOpen(true);
  }

  // A TaraBasa card holds only the learner code: TB26-48293 (or an old TB-ABC12). Never the PIN.
  const looksRight = found ? /^TB(\d{2}-?\d{5}|-[A-Z0-9]{5})$/i.test(found.trim()) : false;

  return (
    <Card number="4" title="Camera: scan a learner card">
      <Text style={styles.small}>Point the camera at a printed learner card QR code. The card holds the code only, never the PIN.</Text>
      <Btn label={open ? 'Close the camera' : 'Open the camera'} onPress={() => (open ? setOpen(false) : openCamera())} />
      {permission && !permission.granted && permission.canAskAgain === false ? (
        <Out bad text="The camera is blocked. Allow it in the phone settings for this app." />
      ) : null}
      {open ? (
        <View style={styles.camera}>
          <CameraView
            style={StyleSheet.absoluteFill}
            facing="back"
            barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
            onBarcodeScanned={(result) => {
              setFound(result.data);
              setOpen(false);
            }}
          />
        </View>
      ) : null}
      {found ? <Out bad={!looksRight} text={looksRight ? `A TaraBasa learner card: ${found}` : `Not a TaraBasa card. It says: ${found}`} /> : null}
    </Card>
  );
}

// ---------------------------------------------------------------- 5. the read aloud voice and 6. an animation

function VoiceAndAnimationCheck() {
  return (
    <Card number="5" title="Read aloud voice and animation">
      <Text style={styles.small}>The phone's own voice reads, and one of the website's animations plays.</Text>
      <Btn label="Tara reads: cat, dog, pig, hen, cow" onPress={() => Speech.speak('cat, dog, pig, hen, cow.', { language: 'en-US', rate: 0.85 })} />
      <Btn label="Stop the voice" onPress={() => Speech.stop()} />
      <View style={styles.lottie}>
        <LottieView source={require('../assets/animations/star-happy.json')} autoPlay loop style={styles.lottieBox} />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  stack: { gap: 10 },
  card: { backgroundColor: '#eaf4ff', borderRadius: 18, padding: 16, gap: 10 },
  cardTitle: { fontSize: 17, fontWeight: '700', color: '#17324d' },
  cardSub: { fontWeight: '700', color: '#17324d', marginTop: 4 },
  small: { fontSize: 13, color: '#56697a' },
  btn: { backgroundColor: '#2f7bd6', borderRadius: 12, padding: 12, alignItems: 'center' },
  btnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  disabled: { opacity: 0.55 },
  out: { fontFamily: 'monospace', fontSize: 12, color: '#17324d', backgroundColor: '#fff', borderRadius: 10, padding: 10 },
  bad: { color: '#b3261e' },
  working: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  option: { backgroundColor: '#fff', borderRadius: 12, padding: 12, borderWidth: 2, borderColor: 'transparent' },
  optionOn: { borderColor: '#dd7014' },
  optionText: { fontWeight: '600', color: '#17324d' },
  passage: { fontSize: 22, lineHeight: 34, color: '#17324d', backgroundColor: '#fff', borderRadius: 12, padding: 12 },
  camera: { height: 260, borderRadius: 14, overflow: 'hidden', backgroundColor: '#000' },
  lottie: { alignItems: 'center' },
  lottieBox: { width: 160, height: 160 },
});
