import { useEffect } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Btn, H2, Icon, P, Panel, Screen, Small } from '../ui';
import { Banner, ListRow, Loading, TonePill, Tile, useLoad } from '../teacher/kit';
import { Card, ChildChips, ChildFace, LevelPill, Meter } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type Props = {
  account: api.Account;
  childId: number | null;
  onPick: (id: number) => void;
  refreshKey: number;
  onProfile: () => void;
  onAdd: () => void;
  go: (to: 'children' | 'progress' | 'activities' | 'alerts') => void;
  /** Tells the app how many alerts are unread (for the badge on the bottom bar) and which child is showing. */
  onLoaded: (home: api.ParentHome) => void;
};

/** "Good morning" and so on, in the family's own time (the Philippines), whatever the phone's clock says. A phone whose engine lacks this just says "Hello". */
function greeting(): string {
  try {
    const hour = Number(new Intl.DateTimeFormat('en-US', { hour: 'numeric', hour12: false, timeZone: 'Asia/Manila' }).format(new Date())) % 24;
    if (Number.isNaN(hour)) return 'Hello';
    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
  } catch {
    return 'Hello';
  }
}

/**
 * Parent Home: one child at a time (pick from the chips). What matters first: a banner if the latest reading was flagged, then the
 * child's week (days in a row, points, words a minute), this week's goal, badges, who started the readings, and the recent ones.
 * Every number comes from the same sources the child's own Home uses, so the two screens never disagree.
 */
export function ParentHome({ account, childId, onPick, refreshKey, onProfile, onAdd, go, onLoaded }: Props) {
  const { data, error, reload } = useLoad(() => api.parentHome(childId), [childId, refreshKey]);
  useEffect(() => {
    if (data) onLoaded(data);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  const c = data?.child ?? null;

  return (
    <Screen withTabBar>
      <View style={st.top}>
        <View style={{ flex: 1 }}>
          <Text maxFontSizeMultiplier={1.2} style={st.hi} numberOfLines={1}>
            Hi, {account.firstName}
          </Text>
          <Small>{data && data.children.length > 0 ? `${greeting()}. Here is how your ${data.children.length === 1 ? 'child is' : 'children are'} doing.` : `${greeting()}. Welcome to TaraBasa AI`}</Small>
        </View>
        <Pressable accessibilityRole="button" accessibilityLabel="My profile" onPress={onProfile} style={st.profile}>
          <Icon name="user-circle" size={s(36)} color={colors.blueDark} />
        </Pressable>
      </View>

      <View style={{ gap: space[14] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting your children" />
        ) : data.children.length === 0 ? (
          <View style={st.empty}>
            <Icon name="users-three" size={s(56)} color={colors.blue} />
            <H2 style={{ textAlign: 'center' }}>No children added yet</H2>
            <P style={{ textAlign: 'center' }}>Add your first child to follow their reading with TaraBasa AI.</P>
            <Btn label="Add your child" icon="plus" onPress={onAdd} style={{ alignSelf: 'stretch' }} />
          </View>
        ) : (
          <>
            <ChildChips children={data.children} selected={data.selected} onPick={onPick} />

            {c?.flagged ? (
              <Banner
                tone="red"
                icon="warning-circle"
                title={`${c.firstName} needs attention`}
                text="Their most recent reading was flagged for extra support. A quick look could help."
                action={<Btn label="See" compact onPress={() => go('progress')} />}
              />
            ) : null}

            {c ? (
              <>
                <Card style={{ gap: space[12] }}>
                  <View style={st.who}>
                    <ChildFace child={c} px={s(64)} />
                    <View style={{ flex: 1, gap: space[4] }}>
                      <Text maxFontSizeMultiplier={1.2} style={st.name} numberOfLines={2}>
                        {c.firstName}
                      </Text>
                      <Small>{[c.grade, c.className ?? 'Not in a class yet'].join(' · ')}</Small>
                      <LevelPill level={c.level} />
                    </View>
                  </View>
                  <View style={{ flexDirection: 'row', gap: space[10] }}>
                    <Tile value={c.summary.dayStreak} label="Days in a row" />
                    <Tile value={c.points} label="Points" />
                    <Tile value={c.wcpm ?? 'None'} label="Words a minute" />
                  </View>
                </Card>

                <Panel style={{ gap: space[8] }}>
                  <H2>This week's goal</H2>
                  <Meter value={c.summary.weeklyCount} max={c.summary.weeklyTarget} tone={c.summary.weeklyMet ? 'green' : 'blue'} />
                  <P style={{ color: colors.ink }}>
                    {c.summary.weeklyMet
                      ? `Goal reached! ${c.firstName} read ${c.summary.weeklyCount} ${c.summary.weeklyCount === 1 ? 'time' : 'times'} this week.`
                      : `${c.summary.weeklyCount} of ${c.summary.weeklyTarget} readings this week. ${c.summary.weeklyLeft} to go.`}
                  </P>
                </Panel>

                <Panel style={{ gap: space[8] }}>
                  <H2>Badges</H2>
                  <P style={{ color: colors.ink }}>
                    {c.summary.badgesEarned} of {c.summary.badgesTotal} earned
                  </P>
                  {c.summary.latestBadges.length > 0 ? (
                    <View style={st.badges}>
                      {c.summary.latestBadges.map((b, i) => (
                        <View key={`${b.name}-${i}`} style={st.badge}>
                          <Icon name={b.icon} size={s(22)} color={colors.goldDeep} />
                          <Text maxFontSizeMultiplier={1.2} style={st.badgeText}>
                            {b.name}
                          </Text>
                        </View>
                      ))}
                    </View>
                  ) : (
                    <Small>No badges yet. They are earned by reading.</Small>
                  )}
                </Panel>

                <View style={{ flexDirection: 'row', gap: space[10] }}>
                  <Source label="From your teacher" s={c.sources.teacher} />
                  <Source label="Started by you" s={c.sources.parent} />
                </View>

                <View style={st.head}>
                  <H2>Recent readings</H2>
                  <Pressable accessibilityRole="button" accessibilityLabel="See all readings" onPress={() => go('progress')} style={st.link}>
                    <Text maxFontSizeMultiplier={1.2} style={st.linkText}>
                      See all
                    </Text>
                  </Pressable>
                </View>
                {c.recent.length === 0 ? (
                  <Small>{`No readings yet for ${c.firstName}. Once they read something, it shows up here.`}</Small>
                ) : (
                  c.recent.map((r) => <Reading key={r.id} r={r} />)
                )}

                <Btn label="Browse extra activities" icon="books" onPress={() => go('activities')} />
              </>
            ) : null}

            <Btn label="Add another child" tone="ghost" compact icon="plus" onPress={onAdd} />
          </>
        )}
      </View>
    </Screen>
  );
}

function Source({ label, s: x }: { label: string; s: { count: number; average: number | null } }) {
  return (
    <View style={st.source}>
      <Text maxFontSizeMultiplier={1.2} style={st.sourceValue}>
        {x.count}
      </Text>
      <Small style={{ textAlign: 'center' }}>{label}</Small>
      <Small style={{ textAlign: 'center' }}>{x.average !== null ? `Average ${x.average}%` : 'No readings'}</Small>
    </View>
  );
}

/** One reading, as the parent sees it: the activity, the accuracy and who started it. Never a harsh word for a low score. */
export function Reading({ r }: { r: api.ParentSession }) {
  const sub = r.flagged ? 'Flagged for extra support' : r.movedText ? `${r.accuracy ?? 0}% accuracy, ${r.movedText}` : r.moved ? `${r.accuracy ?? 0}% accuracy, moved to ${r.moved}` : r.accuracy !== null ? `${r.accuracy}% accuracy` : 'No score';
  return (
    <ListRow
      lead={<Icon name={r.flagged ? 'warning-circle' : 'check-circle'} size={s(28)} color={r.flagged ? colors.bad : colors.ok} />}
      title={r.title}
      sub={`${sub} · ${r.day}`}
      right={<TonePill text={r.source === 'Parent' ? 'You' : 'Teacher'} tone={r.source === 'Parent' ? 'blue' : 'grey'} />}
    />
  );
}

const st = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', gap: space[12], marginBottom: space[12] },
  hi: { fontFamily: fonts.display, fontSize: s(26), lineHeight: s(36), color: colors.orange },
  profile: { width: size.button, height: size.button, alignItems: 'center', justifyContent: 'center' },
  empty: { alignItems: 'center', gap: space[10], paddingVertical: space[24] },
  who: { flexDirection: 'row', alignItems: 'center', gap: space[12] },
  name: { fontFamily: fonts.display, fontSize: s(26), lineHeight: s(34), color: colors.ink },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
  badge: { flexDirection: 'row', alignItems: 'center', gap: space[4], backgroundColor: colors.gold, borderRadius: s(20), paddingHorizontal: space[10], minHeight: s(34) },
  badgeText: { fontFamily: fonts.uiBold, fontSize: s(14), color: colors.warn },
  source: { flex: 1, minWidth: 0, backgroundColor: colors.sky, borderRadius: s(16), alignItems: 'center', justifyContent: 'center', padding: space[10], gap: space[2] },
  sourceValue: { fontFamily: fonts.display, fontSize: s(28), lineHeight: s(34), color: colors.blueDark },
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  link: { minHeight: s(48), minWidth: s(48), alignItems: 'center', justifyContent: 'center', paddingHorizontal: space[8] },
  linkText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.blueDark, textDecorationLine: 'underline' },
});
