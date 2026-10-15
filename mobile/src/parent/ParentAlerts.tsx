import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Segmented } from '../fields';
import { Btn, H2, Icon, Notice, P, Screen, Small } from '../ui';
import { Loading, TopBar, useLoad } from '../teacher/kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type Filter = 'all' | 'attention' | 'routine';

type Props = {
  refreshKey: number;
  /** Tells the tab bar how many alerts are unread. */
  onUnread: (n: number) => void;
};

const DAYS = ['Today', 'Yesterday', 'This week', 'Earlier'];

/**
 * Alerts: what the app tells a parent about their children, newest first, grouped by day (the family's own days). An alert that
 * needs a look is marked in red. Tapping one marks it read; the unread number on the bottom bar follows.
 */
export function ParentAlerts({ refreshKey, onUnread }: Props) {
  const [filter, setFilter] = useState<Filter>('all');
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const { data, error, reload, setData } = useLoad(() => api.parentAlerts(filter), [filter, refreshKey]);

  useEffect(() => {
    if (data) onUnread(data.unread);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  async function read(a: api.ParentAlert) {
    if (a.read) return;
    // Shown as read at once; the server's own count then replaces the guess.
    setData((d) => (d ? { ...d, alerts: d.alerts.map((x) => (x.id === a.id ? { ...x, read: true } : x)), unread: Math.max(0, d.unread - 1) } : d));
    try {
      const r = await api.markParentAlertRead(a.id);
      setData((d) => (d ? { ...d, unread: r.unread } : d));
    } catch (e) {
      setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
      reload();
    }
  }

  async function readAll() {
    if (busy) return;
    setBusy(true);
    setProblem(null);
    try {
      await api.markAllParentAlertsRead();
      reload();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  const groups = data ? DAYS.map((day) => ({ day, items: data.alerts.filter((a) => a.day === day) })).filter((g) => g.items.length > 0) : [];

  return (
    <Screen withTabBar>
      <TopBar title="Alerts" sub={data ? (data.unread === 0 ? 'Nothing new' : `${data.unread} unread`) : undefined} />

      <View style={{ gap: space[12] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting your alerts" />
        ) : (
          <>
            <Segmented
              options={[
                { key: 'all', label: 'All' },
                { key: 'attention', label: 'Attention' },
                { key: 'routine', label: 'Updates' },
              ]}
              value={filter}
              onChange={setFilter}
            />
            {data.unread > 0 ? <Btn label="Mark all as read" tone="ghost" compact loading={busy} onPress={readAll} /> : null}
            {problem ? <Notice text={problem} /> : null}

            {groups.length === 0 ? (
              <View style={{ alignItems: 'center', gap: space[8], paddingVertical: space[24] }}>
                <Icon name="bell" size={s(44)} color={colors.line} />
                <P style={{ textAlign: 'center' }}>{filter === 'all' ? 'No alerts yet. When your child reads, a summary shows here.' : 'Nothing here right now.'}</P>
              </View>
            ) : (
              groups.map((g) => (
                <View key={g.day} style={{ gap: space[8] }}>
                  <H2>{g.day}</H2>
                  {g.items.map((a) => (
                    <Pressable key={a.id} accessibilityRole="button" accessibilityLabel={`${a.read ? '' : 'Unread. '}${a.child ? `${a.child}. ` : ''}${a.message}`} accessibilityHint={a.read ? undefined : 'Marks it as read'} onPress={() => read(a)} style={[st.card, !a.read && st.unread, a.attention && st.attention]}>
                      <Icon name={a.attention ? 'warning-circle' : 'check-circle'} size={s(28)} color={a.attention ? colors.bad : colors.ok} />
                      <View style={{ flex: 1, gap: space[2] }}>
                        {a.child ? <Small>{a.child}</Small> : null}
                        <Text maxFontSizeMultiplier={1.25} style={[st.message, !a.read && { fontFamily: fonts.uiBold }]}>
                          {a.message}
                        </Text>
                      </View>
                      {!a.read ? <View style={st.dot} accessible={false} /> : null}
                    </Pressable>
                  ))}
                </View>
              ))
            )}
          </>
        )}
      </View>
    </Screen>
  );
}

const st = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'center', gap: space[10], minHeight: size.button + s(8), backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, padding: space[12] },
  unread: { backgroundColor: colors.sky, borderColor: colors.sky2 },
  attention: { borderColor: colors.bad },
  message: { fontFamily: fonts.ui, fontSize: s(16), lineHeight: s(21), color: colors.ink },
  dot: { width: s(12), height: s(12), borderRadius: s(6), backgroundColor: colors.blue },
});
