import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Chips, Segmented } from '../fields';
import { Btn, H2, Notice, P, Panel, Screen, Small } from '../ui';
import { Banner, Initial, Loading, TopBar, useLoad } from './kit';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

/**
 * Promotions (Grade 1 and 2 only): release a learner for the next grade, or claim one that another teacher released. A released
 * learner leaves your class but stays an active learner, waiting to be claimed. Claiming puts them in your class of the next grade
 * and sets their grade from that class. Grade 3 has no next grade here.
 */
export function Promotions({ active, onBack }: { active: boolean; onBack: () => void }) {
  const { data, error, reload } = useLoad(() => api.teacherPromotions());
  const [tab, setTab] = useState<'release' | 'claim'>('release');
  const [confirm, setConfirm] = useState<number | null>(null);
  const [pickedClass, setPickedClass] = useState<Record<number, number>>({});
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  async function run(key: string, work: () => Promise<{ message: string }>) {
    if (busy) return;
    setBusy(key);
    setMessage(null);
    setProblem(null);
    try {
      const done = await work();
      setMessage(done.message);
      setConfirm(null);
      reload();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(null);
    }
  }

  return (
    <Screen withTabBar>
      <TopBar title="Promotions" sub="Move learners up to the next grade" onBack={onBack} />

      <View style={{ gap: space[12] }}>
        {!active ? <Banner tone="amber" icon="info" title="Promotions open once you are approved" text="You can look now." /> : null}
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting promotions" />
        ) : (
          <>
            <Segmented
              options={[
                { key: 'release', label: 'Release' },
                { key: 'claim', label: data.claim.length ? `Claim (${data.claim.length})` : 'Claim' },
              ]}
              value={tab}
              onChange={setTab}
            />

            {message ? <Notice tone="info" text={message} /> : null}
            {problem ? <Notice text={problem} /> : null}

            {tab === 'release' ? (
              <>
                <Small>Releasing takes a child out of your class. They stay an active learner until a teacher with a class of the next grade claims them.</Small>
                {data.release.length === 0 ? <P style={{ textAlign: 'center', paddingVertical: space[16] }}>You have no class this school year.</P> : null}
                {data.release.map((c) => (
                  <Panel key={c.id} style={{ gap: space[8] }}>
                    <H2>
                      {c.name} ({c.grade})
                    </H2>
                    {c.learners.length === 0 ? <Small>No learners in this class.</Small> : null}
                    {c.learners.map((l) => (
                      <View key={l.id} style={st.person}>
                        <View style={st.personTop}>
                          <Initial name={l.name} px={s(36)} />
                          <Text maxFontSizeMultiplier={1.25} style={st.personName} numberOfLines={1}>
                            {l.name}
                          </Text>
                        </View>
                        {l.nextGrade === null ? (
                          <Small>Final grade. There is no next grade to move to.</Small>
                        ) : confirm === l.id ? (
                          <View style={{ gap: space[6] }}>
                            <P style={{ color: colors.ink }}>
                              Release {l.name.split(' ')[0]} to {l.nextGrade}? They leave {c.name}.
                            </P>
                            <View style={{ flexDirection: 'row', gap: space[8] }}>
                              <Btn label="Yes, release" compact loading={busy === `r${l.id}`} disabled={busy !== null || !active} onPress={() => run(`r${l.id}`, () => api.releaseLearner(l.id))} style={{ flex: 1 }} />
                              <Btn label="Cancel" tone="ghost" compact onPress={() => setConfirm(null)} style={{ flex: 1 }} />
                            </View>
                          </View>
                        ) : active ? (
                          <Btn label={`Release to ${l.nextGrade}`} tone="ghost" compact onPress={() => setConfirm(l.id)} />
                        ) : null}
                      </View>
                    ))}
                  </Panel>
                ))}
              </>
            ) : (
              <>
                <Small>Learners released by any teacher wait here. You can claim one into your class of the grade they move to.</Small>
                {data.claim.length === 0 ? <P style={{ textAlign: 'center', paddingVertical: space[16] }}>Nobody is waiting to be claimed.</P> : null}
                {data.claim.map((r) => {
                  const chosen = pickedClass[r.id] ?? (r.classes.length === 1 ? r.classes[0].id : null);
                  return (
                    <Panel key={r.id} style={{ gap: space[8] }}>
                      <View style={st.personTop}>
                        <Initial name={r.name} px={s(36)} />
                        <View style={{ flex: 1 }}>
                          <Text maxFontSizeMultiplier={1.25} style={st.personName}>
                            {r.name}
                          </Text>
                          <Small>
                            Moving to {r.nextGrade}
                            {r.releasedBy ? `. Released by ${r.releasedBy}${r.releasedAt ? ` on ${r.releasedAt}` : ''}` : ''}
                          </Small>
                        </View>
                      </View>
                      {r.classes.length === 0 ? (
                        <Small>You have no {r.nextGrade} class this school year, so you cannot claim this learner.</Small>
                      ) : (
                        <>
                          {r.classes.length > 1 ? (
                            <Chips label="Claim into" options={r.classes.map((c) => c.name)} picked={chosen !== null ? [r.classes.find((c) => c.id === chosen)?.name ?? ''] : []} onToggle={(name) => setPickedClass((p) => ({ ...p, [r.id]: r.classes.find((c) => c.name === name)?.id ?? 0 }))} />
                          ) : null}
                          {active ? (
                            <Btn
                              label={chosen !== null ? `Claim into ${r.classes.find((c) => c.id === chosen)?.name}` : 'Pick a class to claim into'}
                              compact
                              disabled={chosen === null || busy !== null}
                              loading={busy === `c${r.id}`}
                              onPress={() => chosen !== null && run(`c${r.id}`, () => api.claimLearner(r.id, chosen))}
                            />
                          ) : null}
                        </>
                      )}
                    </Panel>
                  );
                })}
              </>
            )}
          </>
        )}
      </View>
    </Screen>
  );
}

const st = StyleSheet.create({
  person: { gap: space[6], paddingVertical: space[4] },
  personTop: { flexDirection: 'row', alignItems: 'center', gap: space[10] },
  personName: { flex: 1, fontFamily: fonts.uiBold, fontSize: s(18), color: colors.ink },
});
