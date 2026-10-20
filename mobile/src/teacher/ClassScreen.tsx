import { useCallback, useRef, useState } from 'react';
import { KeyboardAvoidingView, View } from 'react-native';
import * as api from '../api';
import { Field, Segmented } from '../fields';
import { Btn, Busy, H2, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { ClassScanner } from './ClassScanner';
import { Initial, ListRow, Loading, TonePill, TopBar, useLoad } from './kit';
import { space } from '../theme';
import { s } from '../scale';

type Tab = 'learners' | 'groups' | 'activities';

type Props = {
  id: number;
  onBack: () => void;
  onLearner: (learnerId: number) => void;
  onEdit: (card: api.ClassCard) => void;
  refreshKey: number;
  /** What the last action said, such as a learner moved out of this class. */
  flash?: string | null;
};

/** The reading group a Phil-IRI level belongs to, the same split the website uses (Non-reader and Frustration share the first). */
const GROUP_OF: Record<string, 'support' | 'instructional' | 'independent'> = { 'Non-reader': 'support', Frustration: 'support', Instructional: 'instructional', Independent: 'independent' };

/**
 * One class: its learners (with a tag for the ones who need you), its reading groups and what it has been given, plus the two
 * ways to add a child: scan the printed card, or type the last 5 characters of the code. Both go through the website's rules.
 */
export function ClassScreen({ id, onBack, onLearner, onEdit, refreshKey, flash }: Props) {
  const { data, error, reload } = useLoad(() => api.teacherClass(id), [id, refreshKey]);
  const [tab, setTab] = useState<Tab>('learners');
  const [typing, setTyping] = useState(false);
  const [scanning, setScanning] = useState(false);

  const card = data?.class;

  return (
    <Screen withTabBar>
      <TopBar
        title={card?.name ?? 'Class'}
        sub={card ? `${card.grade} · ${card.learnerCount} ${card.learnerCount === 1 ? 'learner' : 'learners'}${data ? ` · taught at the ${data.generalLevel.label} level` : ''}` : undefined}
        onBack={onBack}
        right={card && !card.readOnly && data?.canAdd ? <IconBtn icon="note-pencil" label="Edit class" onPress={() => onEdit(card)} /> : undefined}
      />

      {!data ? (
        <Loading error={error} onRetry={reload} text="Getting the class" />
      ) : (
        <View style={{ gap: space[12] }}>
          <Segmented
            options={[
              { key: 'learners', label: 'Learners' },
              { key: 'groups', label: 'Groups' },
              { key: 'activities', label: 'Activities' },
            ]}
            value={tab}
            onChange={setTab}
          />

          {flash ? <Notice tone="info" text={flash} /> : null}
          {card!.readOnly ? <Notice tone="info" text="This is a past school year, so it can only be read." /> : null}

          {tab === 'learners' ? (
            <>
              {data.canAdd ? (
                <View style={{ gap: space[8] }}>
                  <Btn label="Scan learner cards" icon="scan" onPress={() => setScanning(true)} />
                  <Btn label={typing ? 'Close' : 'Add with a code'} tone="ghost" compact onPress={() => setTyping((v) => !v)} />
                  {typing ? (
                    <AddByCode
                      classId={id}
                      onAdded={(learner) => {
                        setTyping(false);
                        reload();
                        onLearner(learner.id);
                      }}
                    />
                  ) : null}
                </View>
              ) : null}

              {data.learners.length === 0 ? (
                <P style={{ textAlign: 'center', paddingVertical: space[16] }}>
                  {data.canAdd ? 'No learners yet. Scan a card or type a code to add the first one.' : 'There are no learners in this class.'}
                </P>
              ) : (
                data.learners.map((l) => <LearnerRow key={l.id} learner={l} onPress={() => onLearner(l.id)} />)
              )}
            </>
          ) : null}

          {tab === 'groups' ? <Groups learners={data.learners} onLearner={onLearner} /> : null}

          {tab === 'activities' ? (
            data.activities.length === 0 ? (
              <P style={{ textAlign: 'center', paddingVertical: space[16] }}>Nothing has been given to this class yet.</P>
            ) : (
              data.activities.map((a) => (
                <ListRow
                  key={`${a.id}-${a.via}-${a.band}`}
                  title={a.title}
                  sub={a.via === 'class' ? 'Given to the whole class' : a.via === 'band' ? `Given to the ${a.band} group` : `Given through the ${a.tag} focus group`}
                  right={a.level ? <TonePill text={a.level} tone={a.level === 'Easy' ? 'green' : a.level === 'Hard' ? 'red' : 'amber'} /> : undefined}
                />
              ))
            )
          ) : null}
        </View>
      )}

      <ClassScanner
        visible={scanning}
        classId={id}
        className={card?.name ?? 'the class'}
        onClose={() => {
          setScanning(false);
          reload();
        }}
      />
    </Screen>
  );
}

export function LearnerRow({ learner, onPress }: { learner: api.RosterLearner; onPress: () => void }) {
  return (
    <ListRow
      lead={<Initial name={learner.firstName} />}
      title={learner.name}
      sub={[learner.level, learner.step].filter(Boolean).join(' · ')}
      right={learner.tag ? <TonePill text={learner.tag.label} tone={learner.tag.tone} /> : undefined}
      onPress={onPress}
    />
  );
}

function Groups({ learners, onLearner }: { learners: api.RosterLearner[]; onLearner: (id: number) => void }) {
  const sections: { key: string; title: string; sub: string; people: api.RosterLearner[] }[] = [
    { key: 'support', title: 'Needs most support', sub: 'Non-reader and Frustration level', people: [] },
    { key: 'instructional', title: 'Instructional', sub: 'Reads with some help', people: [] },
    { key: 'independent', title: 'Independent', sub: 'Reads on their own', people: [] },
    { key: 'unchecked', title: 'Not checked yet', sub: 'Their first reading check is not done', people: [] },
  ];
  for (const l of learners) (sections.find((x) => x.key === (GROUP_OF[l.level] ?? 'unchecked')) ?? sections[3]).people.push(l);

  return (
    <View style={{ gap: space[14] }}>
      <Small>Groups are made automatically from each child's level, so a child who improves joins the next group. Nobody is moved out of the class.</Small>
      {sections
        .filter((x) => x.people.length > 0)
        .map((x) => (
          <Panel key={x.key} style={{ gap: space[8] }}>
            <H2>
              {x.title} ({x.people.length})
            </H2>
            <Small>{x.sub}</Small>
            {x.people.map((l) => (
              <ListRow key={l.id} lead={<Initial name={l.firstName} px={s(36)} />} title={l.name} sub={l.step ?? undefined} onPress={() => onLearner(l.id)} style={{ minHeight: s(52) }} />
            ))}
          </Panel>
        ))}
    </View>
  );
}

type CodeState = { kind: 'idle' | 'typing' | 'checking' | 'ok' | 'bad'; message: string; retry?: boolean };

/** The last 5 characters of what was typed, the same way the server reads them: whitespace and a leading TB26- are dropped. */
function tailOf(typed: string): string {
  return typed.replace(/\s+/g, '').replace(/^TB[0-9]{0,2}-?/i, '').toUpperCase();
}

function AddByCode({ classId, onAdded }: { classId: number; onAdded: (learner: api.RosterLearner) => void }) {
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
