import { useState } from 'react';
import { View } from 'react-native';
import * as api from '../api';
import { Segmented } from '../fields';
import { Btn, H2, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { AddLearnerSheet } from './AddLearner';
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
 * One class: its learners (with a tag for the ones who need you), its reading groups and what it has been given, plus "Add a learner":
 * scan the printed cards, use a photo of a card, or type the last 5 characters of the code. All three go through the website's rules.
 */
export function ClassScreen({ id, onBack, onLearner, onEdit, refreshKey, flash }: Props) {
  const { data, error, reload } = useLoad(() => api.teacherClass(id), [id, refreshKey]);
  const [tab, setTab] = useState<Tab>('learners');
  const [adding, setAdding] = useState(false);

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
                <Btn label="Add a learner" icon="plus" onPress={() => setAdding(true)} accessibilityHint="Scan cards, use a photo of a card, or type the code" />
              ) : null}

              {data.learners.length === 0 ? (
                <P style={{ textAlign: 'center', paddingVertical: space[16] }}>
                  {data.canAdd ? 'No learners yet. Tap Add a learner to scan a card, use a photo of it, or type the code.' : 'There are no learners in this class.'}
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

      <AddLearnerSheet
        visible={adding}
        classId={id}
        className={card?.name ?? 'the class'}
        grade={card?.grade ?? ''}
        levelLabel={data?.generalLevel.label ?? null}
        onClose={() => {
          setAdding(false);
          reload();
        }}
        onTyped={(learner) => {
          setAdding(false);
          reload();
          onLearner(learner.id);
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
