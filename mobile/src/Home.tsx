import { useRef, useState } from 'react';
import { Alert, Platform, ScrollView, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Circle } from 'react-native-svg';
import type { Home as HomeData } from './api';
import { ReadingPath } from './ReadingPath';
import { useSession } from './session';
import { StatSheet, type StatKind } from './StatSheet';
import { Avatar, Btn, Busy, H2, Icon, Notice, Panel, Pill, Screen, Small } from './ui';
import { colors, fonts, size, space } from './theme';
import { s } from './scale';

/**
 * Home: who is signed in, days in a row and points, the reading path with the child's own step marked, one big button to
 * start reading, then the weekly goal and a small week chart. The numbers come from the same place as the website's Home.
 */
export function Home({ onStartReading, onOpenBadges }: { onStartReading: () => void; onOpenBadges?: () => void }) {
  const { learner, home, homeError, reloadHome, signOut } = useSession();
  // Which number's card is open (the flame or the star at the top). Null = none.
  const [stat, setStat] = useState<StatKind | null>(null);
  const scrollRef = useRef<ScrollView>(null);
  const actionBottom = useRef<number | null>(null);
  const brought = useRef(false);
  const { height } = useWindowDimensions();
  const insets = useSafeAreaInsets();

  // On a short phone the Start button can sit below the bottom bar. Once, as Home opens, scroll just far enough to bring
  // it into view (like the language apps do): the content above stays reachable by scrolling back up.
  function bringStartIntoView(contentTop: number) {
    if (brought.current || actionBottom.current === null) return;
    brought.current = true;
    const visible = height - insets.top - 8 - size.tabBar - insets.bottom - 16;
    const y = contentTop + actionBottom.current - visible;
    if (y > 8) scrollRef.current?.scrollTo({ y, animated: true });
  }

  function confirmLogOut() {
    const message = 'You will need your card or code and your PIN to come back.';
    if (Platform.OS === 'web') {
      if (window.confirm(`Log out? ${message}`)) signOut();
      return;
    }
    Alert.alert('Log out?', message, [
      { text: 'Stay', style: 'cancel' },
      { text: 'Log out', style: 'destructive', onPress: signOut },
    ]);
  }

  return (
    <Screen withTabBar scrollRef={scrollRef}>
      <View style={styles.header}>
        <Avatar name={learner.firstName} px={s(52)} />
        <View style={{ flex: 1 }}>
          <Text numberOfLines={1} maxFontSizeMultiplier={1.2} style={styles.name}>
            {learner.firstName}
          </Text>
          {learner.gradeLevel ? <Small>{learner.gradeLevel}</Small> : null}
        </View>
        <Pill icon="fire" color={colors.orange} text={String(home?.streak.days ?? learner.dayStreak)} label={`${home?.streak.days ?? learner.dayStreak} days in a row`} onPress={() => setStat('streak')} />
        <Pill icon="star" color={colors.goldDeep} text={String(learner.points)} label={`${learner.points} points`} onPress={() => setStat('points')} />
      </View>

      <StatSheet
        kind={stat}
        days={home?.streak.days ?? learner.dayStreak}
        week={home?.streak.week ?? []}
        points={learner.points}
        onClose={() => setStat(null)}
        onViewBadges={() => {
          setStat(null);
          (onOpenBadges ?? onStartReading)();
        }}
        onStartReading={() => {
          setStat(null);
          onStartReading();
        }}
      />

      {!home ? (
        homeError ? (
          <View style={{ gap: space[12], marginTop: space[20] }}>
            <Notice text={homeError} />
            <Btn label="Try Again" onPress={reloadHome} />
          </View>
        ) : (
          <Busy text="Getting your reading path" style={{ marginTop: space[24] }} />
        )
      ) : (
        <View style={{ gap: space[14], marginTop: space[14] }} onLayout={(e) => bringStartIntoView(e.nativeEvent.layout.y)}>
          <ReadingPath path={home.readingPath} progress={home.pathProgress} onStart={onStartReading} onActionBottom={(y) => { actionBottom.current = y; }} />
          <View style={styles.row}>
            <WeeklyGoal goal={home.weeklyGoal} />
            <WeekChart days={home.growth.days} scale={home.growth.scale} />
          </View>
          <Btn label="Log out" icon="sign-out" tone="ghost" compact onPress={confirmLogOut} style={{ alignSelf: 'center', marginTop: space[6] }} />
        </View>
      )}
    </Screen>
  );
}

function WeeklyGoal({ goal }: { goal: HomeData['weeklyGoal'] }) {
  const R = 30;
  const C = 2 * Math.PI * R;
  const share = Math.min(1, goal.target > 0 ? goal.done / goal.target : 0);
  const left = Math.max(0, goal.target - goal.done);
  return (
    <Panel style={styles.half} >
      <View style={styles.goal} accessible accessibilityLabel={`Weekly goal, ${goal.done} of ${goal.target}${goal.met ? ', done' : `, ${left} more to go`}`}>
        <View style={{ width: s(74), height: s(74), alignItems: 'center', justifyContent: 'center' }}>
          <Svg width={s(74)} height={s(74)} viewBox="0 0 74 74" style={{ position: 'absolute', transform: [{ rotate: '-90deg' }] }}>
            <Circle cx={37} cy={37} r={R} stroke={colors.sky2} strokeWidth={9} fill="none" />
            {share > 0 ? (
              <Circle
                cx={37}
                cy={37}
                r={R}
                stroke={goal.met ? colors.goldDeep : colors.orange}
                strokeWidth={9}
                fill="none"
                strokeLinecap="round"
                strokeDasharray={`${C * share} ${C}`}
              />
            ) : null}
          </Svg>
          {goal.met ? <Icon name="trophy" size={s(26)} color={colors.goldDeep} /> : <Text style={styles.goalNum}>{goal.done}/{goal.target}</Text>}
        </View>
        <Text maxFontSizeMultiplier={1.15} style={styles.goalText}>
          Weekly goal{'\n'}
          {goal.met ? 'You did it!' : left === 1 ? '1 more to go' : `${left} more to go`}
        </Text>
      </View>
    </Panel>
  );
}

function WeekChart({ days, scale }: { days: HomeData['growth']['days']; scale: number }) {
  const MAX = s(56);
  return (
    <Panel style={styles.half}>
      <View
        style={styles.bars}
        accessible
        accessibilityLabel={`Readings this week: ${days.map((d) => `${d.label} ${d.count}`).join(', ')}`}
      >
        {days.map((day, i) => (
          <View key={i} style={styles.barCol}>
            <View style={{ height: MAX, justifyContent: 'flex-end', alignSelf: 'stretch' }}>
              <View
                style={[
                  styles.bar,
                  { height: day.count > 0 ? Math.max(8, (day.count / Math.max(1, scale)) * MAX) : 6 },
                  day.count > 0 && { backgroundColor: colors.orange },
                  day.isToday && day.count === 0 && { backgroundColor: colors.orange, opacity: 0.35 },
                ]}
              />
            </View>
            <Text maxFontSizeMultiplier={1.1} style={[styles.barLabel, day.isToday && { color: colors.orangeDark }]}>
              {day.label.charAt(0)}
            </Text>
          </View>
        ))}
      </View>
    </Panel>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: space[8] },
  name: { fontFamily: fonts.displayMedium, fontSize: s(24), lineHeight: s(35), color: colors.ink },
  row: { flexDirection: 'row', gap: space[10], alignItems: 'stretch' },
  half: { flex: 1, padding: space[10], justifyContent: 'center' },
  goal: { alignItems: 'center', gap: space[6] },
  goalNum: { fontFamily: fonts.display, fontSize: s(19), color: colors.ink },
  goalText: { fontFamily: fonts.ui, fontSize: s(14), lineHeight: s(18), color: colors.slate, textAlign: 'center' },
  bars: { flexDirection: 'row', alignItems: 'flex-end', gap: space[4] },
  barCol: { flex: 1, alignItems: 'center', gap: space[4] },
  bar: { alignSelf: 'stretch', backgroundColor: colors.sky2, borderTopLeftRadius: s(6), borderTopRightRadius: s(6), borderBottomLeftRadius: s(3), borderBottomRightRadius: s(3) },
  barLabel: { fontFamily: fonts.ui, fontSize: s(12), color: colors.slate },
});
