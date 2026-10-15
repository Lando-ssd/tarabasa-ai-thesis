import { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Btn, Icon, Notice, P, Screen, Small } from '../ui';
import { Initial, Loading, TonePill, TopBar, useLoad, type Tone } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type Props = {
  /** Only an Active teacher can give an activity out. Marking an alert handled is open to a Pending one too. */
  active: boolean;
  refreshKey: number;
  /** Opens the learner's page in Classes. */
  onLearner: (classId: number, learnerId: number) => void;
  /** Tells the tab bar how many alerts are open. */
  onCount: (n: number) => void;
};

const FILTERS: { key: 'all' | api.AlertKind; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'support', label: 'Needs support' },
  { key: 'start', label: 'Just starting' },
  { key: 'up', label: 'Moving up' },
  { key: 'quiet', label: 'Gone quiet' },
];

const KIND: Record<api.AlertKind, { label: string; tone: Tone }> = {
  support: { label: 'Needs support', tone: 'red' },
  start: { label: 'Just starting to read', tone: 'blue' },
  up: { label: 'Ready to move up', tone: 'green' },
  quiet: { label: 'Gone quiet', tone: 'grey' },
};

/**
 * Alerts: who needs support, who is just starting to read, who moved up or is ready to, and who has gone quiet. Each card says what
 * the app saw, why that is flagged, and one step taken from your own approved activities, which you can give with one tap.
 */
export function Alerts({ active, refreshKey, onLearner, onCount }: Props) {
  const [filter, setFilter] = useState<'all' | api.AlertKind>('all');
  const { data, error, reload } = useLoad(() => api.teacherAlerts(filter), [filter, refreshKey]);
  const [message, setMessage] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    if (data) onCount(data.counts.all);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  async function run(key: string, work: () => Promise<{ message: string }>) {
    if (busy) return;
    setBusy(key);
    setMessage(null);
    setProblem(null);
    try {
      const done = await work();
      setMessage(done.message);
      reload();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(null);
    }
  }

  const labelOf = (f: { key: string; label: string }) => (data ? `${f.label} (${f.key === 'all' ? data.counts.all : data.counts[f.key as api.AlertKind]})` : f.label);

  return (
    <Screen withTabBar>
      <TopBar title="Alerts" sub={data ? (data.counts.all === 0 ? 'Nobody needs you right now' : `${data.counts.all} open`) : undefined} />

      <View style={{ gap: space[12] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Checking your classes" />
        ) : (
          <>
            {/* One row that slides sideways, so five filters never take three rows of the screen. */}
            <ScrollView horizontal showsHorizontalScrollIndicator={false} accessibilityRole="tablist" style={st.filterScroll} contentContainerStyle={st.filterRow}>
              {FILTERS.map((f) => {
                const on = f.key === filter;
                return (
                  <Pressable key={f.key} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => setFilter(f.key)} style={[st.chip, on && st.chipOn]}>
                    <Text maxFontSizeMultiplier={1.25} style={[st.chipText, on && { color: colors.white }]}>
                      {labelOf(f)}
                    </Text>
                  </Pressable>
                );
              })}
            </ScrollView>

            {message ? <Notice tone="info" text={message} /> : null}
            {problem ? <Notice text={problem} /> : null}

            {data.alerts.length === 0 ? (
              <View style={{ alignItems: 'center', gap: space[8], paddingVertical: space[24] }}>
                <Icon name="check-circle" size={s(48)} color={colors.ok} />
                <P style={{ textAlign: 'center' }}>{filter === 'all' ? 'Nothing needs your attention. Every learner who has read is doing fine.' : 'Nothing here right now.'}</P>
              </View>
            ) : (
              data.alerts.map((a) => (
                <AlertCard
                  key={`${a.kind}-${a.learnerId}`}
                  alert={a}
                  active={active}
                  busy={busy}
                  onOpen={() => a.classId !== null && onLearner(a.classId, a.learnerId)}
                  onAssign={(activityId) => run(`assign-${a.learnerId}`, () => api.assignSuggested(a.learnerId, a.kind, activityId))}
                  onHandled={() => run(`handled-${a.learnerId}`, () => api.markAlertHandled(a.learnerId, a.kind))}
                />
              ))
            )}
          </>
        )}
      </View>
    </Screen>
  );
}

function AlertCard({ alert: a, active, busy, onOpen, onAssign, onHandled }: { alert: api.AlertItem; active: boolean; busy: string | null; onOpen: () => void; onAssign: (activityId: number) => void; onHandled: () => void }) {
  const [open, setOpen] = useState(false);
  const kind = a.kind === 'up' && a.variant === 'moved' ? { label: 'Moved up', tone: 'green' as Tone } : KIND[a.kind];
  const first = a.name.split(' ')[0];
  const suggestion = a.suggestion;
  const activity = suggestion?.activity ?? null;

  return (
    <View style={st.card}>
      <Pressable accessibilityRole="button" accessibilityLabel={`${a.name}, ${kind.label}. Open their page`} onPress={onOpen} style={st.head}>
        <Initial name={a.name} px={s(40)} />
        <View style={{ flex: 1, gap: space[2] }}>
          <Text maxFontSizeMultiplier={1.25} style={st.name}>
            {a.name}
          </Text>
          {a.className ? <Small>{a.className}</Small> : null}
        </View>
        <TonePill text={kind.label} tone={kind.tone} />
      </Pressable>

      <Text maxFontSizeMultiplier={1.25} style={st.title}>
        {a.title}
      </Text>
      <P style={{ color: colors.ink }}>{a.evidence}</P>

      {a.why || a.pattern || a.basis ? (
        <>
          <Pressable accessibilityRole="button" accessibilityState={{ expanded: open }} onPress={() => setOpen((v) => !v)} style={st.why}>
            <Text maxFontSizeMultiplier={1.25} style={st.whyText}>
              {open ? 'Hide why this is flagged' : 'Why this is flagged'}
            </Text>
            <Icon name="caret-down" size={s(18)} color={colors.blueDark} />
          </Pressable>
          {open ? (
            <View style={{ gap: space[6] }}>
              {a.why ? <P>{a.why}</P> : null}
              {a.pattern ? <P>{a.pattern}</P> : null}
              {a.basis ? <Small>{a.basis}</Small> : null}
            </View>
          ) : null}
        </>
      ) : null}

      {suggestion ? (
        <View style={st.suggest}>
          <Text maxFontSizeMultiplier={1.25} style={st.suggestLabel}>
            A step to try
          </Text>
          {suggestion.tip ? <P style={{ color: colors.ink }}>{suggestion.tip}</P> : null}
          {activity ? (
            <>
              <Text maxFontSizeMultiplier={1.25} style={st.activityTitle}>
                {activity.title}
              </Text>
              <Small>{[activity.level, activity.words ? `${activity.words} words` : null, activity.skill].filter(Boolean).join(' · ')}</Small>
              {activity.why ? <Small>{activity.why}</Small> : null}
              {active ? (
                <Btn label={`Give it to ${first}`} icon="paper-plane-tilt" compact loading={busy === `assign-${a.learnerId}`} disabled={busy !== null} onPress={() => onAssign(activity.id)} />
              ) : (
                <Small>Giving out activities opens once you are approved.</Small>
              )}
            </>
          ) : (
            <Small>You have no approved activity that suits {first} yet. Write one for their class from Activities.</Small>
          )}
        </View>
      ) : null}

      <Btn label="Mark handled" tone="ghost" compact loading={busy === `handled-${a.learnerId}`} disabled={busy !== null} onPress={onHandled} accessibilityHint="Hides this alert until the learner reads again" />
    </View>
  );
}

const st = StyleSheet.create({
  filterScroll: { flexGrow: 0, marginHorizontal: -size.gutter },
  filterRow: { gap: space[8], paddingHorizontal: size.gutter },
  chip: { minHeight: size.touch, paddingHorizontal: space[16], borderRadius: s(24), backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center' },
  chipOn: { backgroundColor: colors.ink },
  chipText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.blueDark },
  card: { backgroundColor: colors.white, borderRadius: s(18), borderWidth: 2, borderColor: colors.line, borderBottomWidth: s(5), padding: space[14], gap: space[8] },
  head: { flexDirection: 'row', alignItems: 'center', gap: space[10], minHeight: s(48) },
  name: { fontFamily: fonts.uiBold, fontSize: s(18), lineHeight: s(23), color: colors.ink },
  title: { fontFamily: fonts.display, fontSize: s(20), lineHeight: s(26), color: colors.ink },
  why: { flexDirection: 'row', alignItems: 'center', gap: space[6], minHeight: s(44) },
  whyText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.blueDark },
  suggest: { backgroundColor: colors.sky, borderRadius: s(14), padding: space[12], gap: space[6] },
  suggestLabel: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.blueDark },
  activityTitle: { fontFamily: fonts.uiBold, fontSize: s(17), lineHeight: s(22), color: colors.ink },
});
