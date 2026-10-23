import { useCallback, useRef, useState } from 'react';
import { KeyboardAvoidingView, Modal, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as api from '../api';
import { noCardMessage, readCardsFromPhotos } from '../cardPhoto';
import { Field } from '../fields';
import { Btn, Busy, H1, Icon, IconBtn, Notice, P, Panel, Small } from '../ui';
import { ClassScanner } from './ClassScanner';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type SheetProps = {
  visible: boolean;
  classId: number;
  className: string;
  grade: string;
  /** The class's general reading level in Phil-IRI words ("Instructional"), as the server works it out. */
  levelLabel: string | null;
  /** Closing the sheet (the X, Done or the phone's back button). The class list is fetched again by the caller. */
  onClose: () => void;
  /** A learner was added by typing the code: the caller opens that child's page. */
  onTyped: (learner: api.RosterLearner) => void;
};

type Outcome = { key: number; ok: boolean; text: string };

/**
 * "Add a learner": the same window the website has, in the same order. Scan the printed cards one after another, use a photo of
 * the card (a photo from the phone's gallery, for a parent who sent a picture of it), or type the last 5 characters of the code.
 * All three go through the same server rules: one grade per class, the reading level guard, and the parent is told.
 */
export function AddLearnerSheet({ visible, classId, className, grade, levelLabel, onClose, onTyped }: SheetProps) {
  const insets = useSafeAreaInsets();
  const [scanning, setScanning] = useState(false);
  const [working, setWorking] = useState(false);
  const [outcomes, setOutcomes] = useState<Outcome[]>([]);
  const counter = useRef(0);

  function note(list: Outcome[], ok: boolean, text: string) {
    list.push({ key: ++counter.current, ok, text });
    setOutcomes([...list]);
  }

  async function addFromPhotos() {
    if (working) return;
    setWorking(true);
    setOutcomes([]);
    const list: Outcome[] = [];
    try {
      const r = await readCardsFromPhotos(true);
      if (r.kind === 'cancelled') return;
      if (r.kind === 'error') return note(list, false, r.message);
      if (r.codes.length === 0) return note(list, false, noCardMessage(r.photos));
      for (const code of r.codes) {
        try {
          const joined = await api.joinLearner(classId, code);
          note(list, joined.ok, joined.ok ? `${joined.learner.name} added to ${className}` : joined.message);
        } catch (e) {
          note(list, false, e instanceof Error ? e.message : 'Something went wrong. Please try again.');
        }
      }
      const missed = r.unreadable + r.foreign;
      if (missed > 0) note(list, false, `${missed} ${missed === 1 ? 'photo had' : 'photos had'} no readable learner card.`);
    } finally {
      setWorking(false);
    }
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <View style={[st.root, { paddingTop: insets.top + 8, paddingBottom: Math.max(insets.bottom, 12) }]}>
        <View style={st.top}>
          <IconBtn icon="x" label="Close" onPress={onClose} />
          <View style={{ flex: 1 }}>
            <H1>Add a learner</H1>
            <Small>{`to ${className} · ${grade}`}</Small>
          </View>
        </View>

        <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={st.body}>
          <P>Scan each learner's card, use a photo of the card, or type the code. A learner can only be in one class at a time.</P>

          <Btn label="Scan QR codes" icon="scan" onPress={() => setScanning(true)} accessibilityHint="Opens the camera. Every card you scan is added." />
          <Btn label={working ? 'Reading the photos' : 'Use a photo of the code'} icon="camera" tone="ghost" onPress={addFromPhotos} loading={working} accessibilityHint="Choose a photo of a learner card from your photos" />

          {working ? <Busy text="Reading the photos" /> : null}
          {outcomes.length > 0 ? (
            <View style={{ gap: space[6] }} accessibilityLiveRegion="polite">
              {outcomes.map((o) => (
                <View key={o.key} style={st.outcome}>
                  <Icon name={o.ok ? 'check-circle' : 'x-circle'} size={s(22)} color={o.ok ? colors.ok : colors.bad} />
                  <Text style={st.outcomeText}>{o.text}</Text>
                </View>
              ))}
            </View>
          ) : null}

          <View style={st.divider} accessibilityRole="header">
            <View style={st.line} />
            <Text style={st.or}>OR TYPE THE CODE</Text>
            <View style={st.line} />
          </View>

          <Notice tone="info" text={`One class, one grade. This is a ${grade} class, so only ${grade} learners can be added. A learner of another grade goes in a class of their own grade.`} />
          {levelLabel ? (
            <Small>{`This class is taught at the ${levelLabel} level. A learner who already reads at the Independent level cannot be added to a class taught at a lower level, even in the same grade. They belong in a class taught at a higher level.`}</Small>
          ) : null}

          <AddByCode classId={classId} onAdded={onTyped} />
        </ScrollView>

        <Btn label="Done" tone="ghost" onPress={onClose} style={{ marginHorizontal: size.gutter }} />
      </View>

      <ClassScanner visible={scanning} classId={classId} className={className} onClose={() => setScanning(false)} />
    </Modal>
  );
}

const st = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.white },
  top: { flexDirection: 'row', alignItems: 'center', gap: space[12], paddingHorizontal: size.gutter, paddingBottom: space[8] },
  body: { padding: size.gutter, gap: space[12], paddingBottom: space[24] },
  divider: { flexDirection: 'row', alignItems: 'center', gap: space[10], marginVertical: space[4] },
  line: { flex: 1, height: 2, backgroundColor: colors.line, borderRadius: 1 },
  or: { fontFamily: fonts.ui, fontSize: s(14), letterSpacing: 1.2, color: colors.slate },
  outcome: { flexDirection: 'row', alignItems: 'flex-start', gap: space[8] },
  outcomeText: { flex: 1, fontFamily: fonts.ui, fontSize: s(16), lineHeight: s(22), color: colors.ink },
});

type CodeState = { kind: 'idle' | 'typing' | 'checking' | 'ok' | 'bad'; message: string; retry?: boolean };

/** The last 5 characters of what was typed, the same way the server reads them: whitespace and a leading TB26- are dropped. */
function tailOf(typed: string): string {
  return typed.replace(/\s+/g, '').replace(/^TB[0-9]{0,2}-?/i, '').toUpperCase();
}

export function AddByCode({ classId, onAdded }: { classId: number; onAdded: (learner: api.RosterLearner) => void }) {
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [state, setState] = useState<CodeState>({ kind: 'idle', message: 'Type the last 5 characters of the code. Add turns on when the code can be added to this class.' });
  const [okFor, setOkFor] = useState<string | null>(null);
  const seq = useRef(0);

  const check = useCallback(
    (typed: string) => {
      const mine = ++seq.current;
      const tail = tailOf(typed);
      setOkFor(null);
      setError(null);

      if (tail.length === 0) return setState({ kind: 'idle', message: 'Type the last 5 characters of the code. Add turns on when the code can be added to this class.' });
      if (tail.length < 5) return setState({ kind: 'typing', message: `Keep typing: ${5 - tail.length} more character${5 - tail.length === 1 ? '' : 's'}.` });
      if (tail.length > 5 || !/^[A-Z0-9]{5}$/.test(tail)) return setState({ kind: 'bad', message: 'Type only the last 5 characters of the code, like 48293.' });

      setState({ kind: 'checking', message: 'Checking this code...' });
      // A short pause so a fast typist does not send one request for every key.
      setTimeout(async () => {
        if (mine !== seq.current) return;
        try {
          const result = await api.checkLearnerCode(classId, typed);
          if (mine !== seq.current) return; // the code changed while the answer was on its way
          if (result.ok) {
            setOkFor(tail);
            setState({ kind: 'ok', message: result.message });
          } else {
            setState({ kind: 'bad', message: result.message });
          }
        } catch (e) {
          if (mine !== seq.current) return;
          setState({ kind: 'bad', message: e instanceof Error ? e.message : 'Could not check this code. Check the internet, then press Check again.', retry: true });
        }
      }, 300);
    },
    [classId],
  );

  const ready = state.kind === 'ok' && okFor !== null && okFor === tailOf(code);

  async function add() {
    if (busy || !ready) return;
    setBusy(true);
    setError(null);
    try {
      const result = await api.joinLearner(classId, code);
      if (result.ok) onAdded(result.learner);
      else {
        // The server asks again when Add is pressed, so a rare change in between (another teacher added the child) is still caught here.
        setOkFor(null);
        setState({ kind: 'bad', message: result.message });
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView behavior="padding">
      <Panel style={{ gap: space[10] }}>
        <Field
          label="Last 5 characters of the code"
          value={code}
          onChangeText={(t) => {
            const up = t.toUpperCase();
            setCode(up);
            check(up);
          }}
          placeholder="48293"
          autoCapitalize="characters"
          maxLength={12}
          returnKeyType="done"
          onSubmitEditing={add}
          hint="The code is on the child's card, like TB26-48293. Type the last 5 characters, or the whole code."
        />
        {/* What the server says about this code. The Add button stays off until it says yes; there is nothing to press but Back until then. */}
        <Notice tone={state.kind === 'bad' ? 'error' : state.kind === 'ok' ? 'good' : 'info'} text={state.message} />
        {state.retry ? <Btn label="Check again" tone="ghost" compact onPress={() => check(code)} /> : null}
        {error ? <Notice text={error} /> : null}
        {busy ? <Busy text="Looking for the learner" /> : null}
        <Btn label="Add to this class" onPress={add} disabled={!ready} loading={busy} accessibilityHint={ready ? undefined : 'Turns on when the code can be added to this class'} />
      </Panel>
    </KeyboardAvoidingView>
  );
}
