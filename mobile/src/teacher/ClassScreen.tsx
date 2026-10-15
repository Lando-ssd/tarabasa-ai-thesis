import { useState } from 'react';
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
};

/** The reading group a Phil-IRI level belongs to, the same split the website uses (Non-reader and Frustration share the first). */
const GROUP_OF: Record<string, 'support' | 'instructional' | 'independent'> = { 'Non-reader': 'support', Frustration: 'support', Instructional: 'instructional', Independent: 'independent' };

/**
 * One class: its learners (with a tag for the ones who need you), its reading groups and what it has been given, plus the two
 * ways to add a child: scan the printed card, or type the last 5 characters of the code. Both go through the website's rules.
 */
export function ClassScreen({ id, onBack, onLearner, onEdit, refreshKey }: Props) {
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

function AddByCode({ classId, onAdded }: { classId: number; onAdded: (learner: api.RosterLearner) => void }) {
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function add() {
    if (busy || !code.trim()) return;
    setBusy(true);
    setError(null);
    try {
      const result = await api.joinLearner(classId, code);
      if (result.ok) onAdded(result.learner);
      else setError(result.message);
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
          onChangeText={(t) => setCode(t.toUpperCase())}
          placeholder="48293"
          autoCapitalize="characters"
          maxLength={12}
          returnKeyType="done"
          onSubmitEditing={add}
          hint="The code is on the child's card, like TB26-48293. Type the last 5 characters, or the whole code."
        />
        {error ? <Notice text={error} /> : null}
        {busy ? <Busy text="Looking for the learner" /> : null}
        <Btn label="Add to this class" onPress={add} disabled={!code.trim()} loading={busy} />
      </Panel>
    </KeyboardAvoidingView>
  );
}
