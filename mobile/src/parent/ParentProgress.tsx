import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { AccountAvatar } from '../AccountAvatar';
import { Avatar, H2, Icon, P, Panel, Screen, Small } from '../ui';
import { Loading, TonePill, Tile, TopBar, useLoad } from '../teacher/kit';
import { Card, ChildChips, ChildFace, LevelPill, Meter } from './kit';
import { Reading } from './ParentHome';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

type Props = {
  account: api.Account;
  childId: number | null;
  onPick: (id: number) => void;
  refreshKey: number;
  onProfile: () => void;
  go: (to: 'activities') => void;
};

/**
 * Progress: how one child is reading, in a parent's words. The level (Phil-IRI) with a plain sentence, one thing to practise at home
 * when the readings show a clear pattern, the week, a small chart of recent accuracy and the history. Nothing is invented: with too
 * few readings it says so.
 */
export function ParentProgress({ account, childId, onPick, refreshKey, onProfile, go }: Props) {
  const { data, error, reload } = useLoad(() => api.parentProgress(childId), [childId, refreshKey]);
  const c = data?.child ?? null;

  return (
    <Screen withTabBar>
      <TopBar
        title="Progress"
        sub={c ? `${c.firstName}, ${c.readings} ${c.readings === 1 ? 'reading' : 'readings'} so far` : undefined}
        right={
          <Pressable accessibilityRole="button" accessibilityLabel="My profile" onPress={onProfile} style={st.profile}>
            <AccountAvatar account={account} px={s(44)}>
              <Avatar name={account.firstName} px={s(44)} />
            </AccountAvatar>
          </Pressable>
        }
      />

      <View style={{ gap: space[14] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting the progress" />
        ) : !c ? (
          <P style={{ textAlign: 'center', paddingVertical: space[24] }}>Add a child to see how they are reading.</P>
        ) : (
          <>
            <ChildChips children={data.children} selected={data.selected} onPick={onPick} />

            <Card>
              <View style={st.who}>
                <ChildFace child={c} px={s(52)} />
                <View style={{ flex: 1, gap: space[4] }}>
                  <H2>{c.level.stepNumber === undefined ? 'Reading level' : 'Reading step'}</H2>
                  <LevelPill level={c.level} />
                </View>
              </View>
              <P style={{ color: colors.ink }}>{c.level.plain}</P>
              {c.level.step ? <Small>{c.level.step}</Small> : null}
              {c.level.stepNumber === undefined ? (
                <Small>Levels follow the Department of Education's reading levels (Phil-IRI). They describe where reading is today, not what a child can become.</Small>
              ) : (
                <Small>{`These are the same four steps ${c.firstName} sees on their own Home screen: Letter Explorer, Word Builder, Sentence Reader and Story Reader.`}</Small>
              )}
            </Card>

            {c.home ? (
              <Panel style={{ gap: space[8] }}>
                <H2>Practise at home</H2>
                <P style={{ color: colors.ink }}>{c.home.say}</P>
                <P style={{ color: colors.ink }}>{c.home.tip}</P>
                {c.home.words.length > 0 ? (
                  <View style={{ gap: space[6] }}>
                    <Small>Words to practise together</Small>
                    <View style={st.words}>
                      {c.home.words.map((w) => (
                        <TonePill key={w} text={w} tone="amber" />
                      ))}
                    </View>
                  </View>
                ) : null}
                <Small>
                  {c.home.early ? 'This is early, from only a few readings. ' : ''}Practises {c.home.codeText} ({c.home.code}).
                </Small>
              </Panel>
            ) : null}

            <View style={{ flexDirection: 'row', gap: space[10] }}>
              <Tile value={c.summary.dayStreak} label="Days in a row" />
              <Tile value={`${c.summary.weeklyCount}/${c.summary.weeklyTarget}`} label="This week" />
              <Tile value={c.summary.badgesEarned} label="Badges" />
            </View>
            <Meter value={c.summary.weeklyCount} max={c.summary.weeklyTarget} tone={c.summary.weeklyMet ? 'green' : 'blue'} />

            <View style={{ flexDirection: 'row', gap: space[10] }}>
              <Source label="From your teacher" x={c.sources.teacher} />
              <Source label="Started by you" x={c.sources.parent} />
            </View>

            <View style={{ gap: space[8] }}>
              <H2>Accuracy of recent readings</H2>
              <Trend values={c.trend} />
            </View>

            <H2>History</H2>
            {c.history.length === 0 ? (
              <View style={{ gap: space[8] }}>
                <Small>{`No readings yet for ${c.firstName}. Once they read something, it shows up here.`}</Small>
                <Pressable accessibilityRole="button" onPress={() => go('activities')} style={st.link}>
                  <Text maxFontSizeMultiplier={1.2} style={st.linkText}>
                    Browse extra activities
                  </Text>
                </Pressable>
              </View>
            ) : (
              c.history.map((r) => <Reading key={r.id} r={r} />)
            )}
          </>
        )}
      </View>
    </Screen>
  );
}

function Source({ label, x }: { label: string; x: { count: number; average: number | null } }) {
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

/** One bar for each recent reading, as tall as its accuracy. Said in words underneath, so the chart is never the only way to read it. */
function Trend({ values }: { values: (number | null)[] }) {
  if (values.length === 0) return <Small>The chart appears after the first reading.</Small>;
  const known = values.filter((v): v is number => v !== null);
  const latest = known[known.length - 1];
  const first = known[0];
  const words =
    known.length < 2 ? `One reading so far, ${latest}%.` : latest > first ? `Up from ${first}% to ${latest}% over these readings.` : latest < first ? `Down from ${first}% to ${latest}% over these readings.` : `Steady at ${latest}% over these readings.`;
  return (
    <View style={{ gap: space[8] }}>
      <View style={st.chart} accessible accessibilityLabel={`Accuracy of the last ${values.length} readings. ${words}`}>
        {values.map((v, i) => (
          <View key={i} style={st.barSlot}>
            <View style={[st.bar, { height: `${Math.max(4, v ?? 0)}%`, backgroundColor: (v ?? 0) >= 90 ? colors.ok : (v ?? 0) >= 70 ? colors.blue : colors.warn }]} />
          </View>
        ))}
      </View>
      <Small>{words}</Small>
    </View>
  );
}

const st = StyleSheet.create({
  profile: { width: s(52), height: s(52), alignItems: 'center', justifyContent: 'center' },
  who: { flexDirection: 'row', alignItems: 'center', gap: space[12] },
  words: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
  source: { flex: 1, minWidth: 0, backgroundColor: colors.sky, borderRadius: s(16), alignItems: 'center', justifyContent: 'center', padding: space[10], gap: space[2] },
  sourceValue: { fontFamily: fonts.display, fontSize: s(28), lineHeight: s(34), color: colors.blueDark },
  chart: { flexDirection: 'row', alignItems: 'flex-end', gap: space[4], height: s(110), backgroundColor: colors.sky, borderRadius: s(14), padding: space[10] },
  barSlot: { flex: 1, minWidth: 0, height: '100%', justifyContent: 'flex-end' },
  bar: { width: '100%', borderRadius: s(4) },
  link: { minHeight: s(48), justifyContent: 'center' },
  linkText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.blueDark, textDecorationLine: 'underline' },
});
