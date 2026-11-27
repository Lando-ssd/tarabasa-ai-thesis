import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Segmented } from '../fields';
import { Btn, Icon, Notice, P, Screen, Small } from '../ui';
import { Loading, TonePill, TopBar, useLoad, type Tone } from '../teacher/kit';
import { ActivityFlow, type FlowView, type PayMode } from './ActivityFlow';
import { ChildChips } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type Filter = 'all' | 'free' | 'paid' | 'unlocked';

const LEVEL_TONE: Record<string, Tone> = { Easy: 'green', Medium: 'amber', Hard: 'red' };

type Props = {
  childId: number | null;
  onPick: (id: number) => void;
  refreshKey: number;
  onAdd: () => void;
};

/**
 * Activities: what other teachers shared in the Open Repository, the ones that suit the child first (their reading level and the
 * kind of mistake they keep making), with a free or paid unlock for one child at a time and a star rating once unlocked. Tapping
 * an activity opens it (ActivityFlow). A paid one is paid with the demo checkout when the server runs it (pretend money), or the
 * old simulated step; nothing real is ever charged. An unlocked activity appears in the child's own app as extra practice.
 */
export function ParentActivities({ childId, onPick, refreshKey, onAdd }: Props) {
  const [filter, setFilter] = useState<Filter>('all');
  const [best, setBest] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [confirm, setConfirm] = useState<number | null>(null);
  const [view, setView] = useState<FlowView>(null);
  const { data, error, reload } = useLoad(() => api.parentRepository(childId, filter, best), [childId, filter, best, refreshKey]);

  const kid = data?.children.find((c) => c.id === data.selected) ?? null;
  const mode: PayMode = data?.payments?.mode ?? 'off';
  const opened = view && view.kind !== 'history' ? (data?.listings.find((l) => l.id === view.id) ?? null) : null;

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
      const fields = api.fieldErrors(e);
      setProblem(fields.unlock ?? (e instanceof Error ? e.message : 'Something went wrong. Please try again.'));
    } finally {
      setBusy(null);
    }
  }

  return (
    <Screen withTabBar>
      <TopBar title="Activities" sub="Shared by teachers" />

      <View style={{ gap: space[12] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting the activities" />
        ) : !kid ? (
          <View style={{ gap: space[10], alignItems: 'center', paddingVertical: space[20] }}>
            <P style={{ textAlign: 'center' }}>Add a child first. Activities are unlocked for one child at a time.</P>
            <Btn label="Add your child" icon="plus" onPress={onAdd} style={{ alignSelf: 'stretch' }} />
          </View>
        ) : (
          <>
            <ChildChips children={data.children} selected={data.selected} onPick={onPick} />

            <Segmented
              options={[
                { key: 'all', label: 'All' },
                { key: 'free', label: 'Free' },
                { key: 'paid', label: 'Paid' },
                { key: 'unlocked', label: 'Unlocked' },
              ]}
              value={filter}
              onChange={setFilter}
            />

            {data.hasMatching ? (
              <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: best }} onPress={() => setBest((v) => !v)} style={st.only}>
                <View style={[st.box, best && st.boxOn]}>{best ? <Icon name="check" size={s(18)} color={colors.white} /> : null}</View>
                <Text maxFontSizeMultiplier={1.25} style={st.onlyText}>
                  Only what suits {kid.firstName}
                </Text>
              </Pressable>
            ) : (
              <Small>{kid.firstName} has not read enough yet for the app to say what suits them. The list is in the order teachers shared it.</Small>
            )}

            {mode === 'demo' ? <Btn label="Payment history" tone="ghost" compact onPress={() => setView({ kind: 'history' })} /> : null}

            {message ? <Notice tone="info" text={message} /> : null}
            {problem ? <Notice text={problem} /> : null}

            {data.listings.length === 0 ? (
              <P style={{ textAlign: 'center', paddingVertical: space[16] }}>{best || filter !== 'all' ? 'Nothing matches. Try another filter.' : 'Teachers have not shared any activities yet.'}</P>
            ) : (
              data.listings.map((l) => (
                <Listing
                  key={l.id}
                  l={l}
                  first={kid.firstName}
                  busy={busy}
                  confirming={confirm === l.id}
                  onConfirm={() => setConfirm(l.id)}
                  onCancel={() => setConfirm(null)}
                  onUnlock={() => run(`u${l.id}`, () => api.unlockListing(l.id, kid.id))}
                  onRate={(n) => run(`r${l.id}`, () => api.rateListing(l.id, kid.id, n))}
                  mode={mode}
                  onOpen={() => {
                    setProblem(null);
                    setView({ kind: 'detail', id: l.id });
                  }}
                  onCheckout={() => setView({ kind: 'checkout', id: l.id })}
                />
              ))
            )}
            <ActivityFlow
              view={view}
              setView={setView}
              listing={opened}
              learnerId={kid.id}
              first={kid.firstName}
              mode={mode}
              busy={busy !== null}
              problem={problem}
              onUnlock={(l) => run(`u${l.id}`, () => api.unlockListing(l.id, kid.id))}
              onRate={(l, n) => run(`r${l.id}`, () => api.rateListing(l.id, kid.id, n))}
              onPaid={reload}
            />
          </>
        )}
      </View>
    </Screen>
  );
}

function Listing({ l, first, busy, confirming, onConfirm, onCancel, onUnlock, onRate, mode, onOpen, onCheckout }: { l: api.ParentListing; first: string; busy: string | null; confirming: boolean; onConfirm: () => void; onCancel: () => void; onUnlock: () => void; onRate: (n: number) => void; mode: PayMode; onOpen: () => void; onCheckout: () => void }) {
  return (
    // The whole card opens the activity. It is not itself announced as a button: the buttons inside stay reachable on their own.
    <Pressable accessible={false} onPress={onOpen} style={({ pressed }) => [st.card, pressed && { opacity: 0.85 }]}>
      <Text maxFontSizeMultiplier={1.25} style={st.title}>
        {l.title}
      </Text>
      <Small>{[l.grade, l.skill, l.words ? `${l.words} words` : null].filter(Boolean).join(' · ')}</Small>
      <View style={st.tags}>
        {l.level ? <TonePill text={l.level} tone={LEVEL_TONE[l.level] ?? 'grey'} /> : null}
        {l.unlocked ? <TonePill text="Unlocked" tone="green" /> : <TonePill text={l.price} tone={l.paid ? 'amber' : 'green'} />}
        {l.matchesLevel ? <TonePill text={`Right level for ${first}`} tone="blue" /> : null}
        {l.matchesPattern ? <TonePill text={`Practises ${l.matchesPattern}`} tone="blue" /> : null}
      </View>
      {l.teacher ? <Small>Shared by {l.teacher}</Small> : null}
      {l.stars !== null ? (
        <Small>
          Rated {l.stars} of 5 by {l.ratings} {l.ratings === 1 ? 'parent' : 'parents'}
        </Small>
      ) : null}

      {l.unlocked ? (
        <View style={{ gap: space[6] }}>
          <Small>{first} can read this from their own app, under extra practice.</Small>
          <View style={st.stars} accessibilityRole="radiogroup" accessibilityLabel="Your rating">
            {[1, 2, 3, 4, 5].map((n) => {
              const on = (l.mine?.rating ?? 0) >= n;
              return (
                <Pressable key={n} accessibilityRole="radio" accessibilityState={{ selected: l.mine?.rating === n }} accessibilityLabel={`${n} ${n === 1 ? 'star' : 'stars'}`} disabled={busy !== null} onPress={() => onRate(n)} style={st.star}>
                  <Icon name="star" size={s(30)} color={on ? colors.goldDeep : colors.line} />
                </Pressable>
              );
            })}
          </View>
          <Small>{l.mine ? `You rated this ${l.mine.rating} of 5. Tap a star to change it.` : 'Tap a star to rate it.'}</Small>
        </View>
      ) : confirming ? (
        <View style={{ gap: space[8] }}>
          <P style={{ color: colors.ink }}>
            Unlock this for {l.price} for {first}? This is a simulated payment, so nothing is charged.
          </P>
          <View style={{ flexDirection: 'row', gap: space[8] }}>
            <Btn label="Yes, unlock" compact loading={busy === `u${l.id}`} disabled={busy !== null} onPress={onUnlock} style={{ flex: 1 }} />
            <Btn label="Cancel" tone="ghost" compact onPress={onCancel} style={{ flex: 1 }} />
          </View>
        </View>
      ) : (
        <Btn label={l.paid ? `Unlock for ${l.price}` : `Unlock for ${first}`} icon="lock-simple" tone={l.paid ? 'blue' : 'orange'} compact loading={busy === `u${l.id}`} disabled={busy !== null} onPress={!l.paid ? onUnlock : mode === 'demo' ? onCheckout : mode === 'off' ? onConfirm : onOpen} />
      )}
      <Btn label={l.unlocked ? 'Open activity' : 'View details'} tone="ghost" compact onPress={onOpen} />
    </Pressable>
  );
}

const st = StyleSheet.create({
  card: { backgroundColor: colors.white, borderRadius: s(18), borderWidth: 2, borderColor: colors.line, borderBottomWidth: s(5), padding: space[14], gap: space[8] },
  title: { fontFamily: fonts.display, fontSize: s(20), lineHeight: s(26), color: colors.ink },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
  stars: { flexDirection: 'row', gap: space[2] },
  star: { width: size.touch, height: size.touch, alignItems: 'center', justifyContent: 'center' },
  only: { flexDirection: 'row', alignItems: 'center', gap: space[10], minHeight: size.touch },
  box: { width: s(28), height: s(28), borderRadius: s(8), borderWidth: 2, borderColor: colors.line, backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  boxOn: { backgroundColor: colors.blue, borderColor: colors.blue },
  onlyText: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.ink, flex: 1 },
});
