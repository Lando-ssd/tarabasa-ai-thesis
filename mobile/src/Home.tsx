import { Alert, Platform, StyleSheet, Text, View } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import type { Home as HomeData } from './api';
import { useSession } from './session';
import { Avatar, Btn, Busy, H2, Icon, Notice, Panel, Pill, Screen, Small } from './ui';
import { colors, fonts } from './theme';

/**
 * Home: who is signed in, days in a row and points, the reading path with the child's own step marked, one big button to
 * start reading, then the weekly goal and a small week chart. The numbers come from the same place as the website's Home.
 */
export function Home({ onStartReading }: { onStartReading: () => void }) {
  const { learner, home, homeError, reloadHome, signOut } = useSession();

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
    <Screen withTabBar>
      <View style={styles.header}>
        <Avatar name={learner.firstName} px={52} />
        <View style={{ flex: 1 }}>
          <Text numberOfLines={1} maxFontSizeMultiplier={1.2} style={styles.name}>
            {learner.firstName}
          </Text>
          {learner.gradeLevel ? <Small>{learner.gradeLevel}</Small> : null}
        </View>
        <Pill icon="fire" color={colors.orange} text={String(home?.streak.days ?? learner.dayStreak)} label={`${home?.streak.days ?? learner.dayStreak} days in a row`} />
        <Pill icon="star" color={colors.goldDeep} text={String(learner.points)} label={`${learner.points} points`} />
      </View>

      {!home ? (
        homeError ? (
          <View style={{ gap: 12, marginTop: 20 }}>
            <Notice text={homeError} />
            <Btn label="Try Again" onPress={reloadHome} />
          </View>
        ) : (
          <Busy text="Getting your reading path" style={{ marginTop: 24 }} />
        )
      ) : (
        <View style={{ gap: 14, marginTop: 14 }}>
          <ReadingPath path={home.readingPath} />
          <Btn label="Start Reading" icon="book-open" onPress={onStartReading} />
          <View style={styles.row}>
            <WeeklyGoal goal={home.weeklyGoal} />
            <WeekChart days={home.growth.days} scale={home.growth.scale} />
          </View>
          <Btn label="Log out" icon="sign-out" tone="ghost" compact onPress={confirmLogOut} style={{ alignSelf: 'center', marginTop: 6 }} />
        </View>
      )}
    </Screen>
  );
}

function ReadingPath({ path }: { path: HomeData['readingPath'] }) {
  const current = path.find((p) => p.isCurrent)?.step ?? 0;
  return (
    <Panel style={{ gap: 8 }}>
      <Small>Your Reading Path</Small>
      {path.map((node) => {
        const done = current > 0 && node.step < current;
        const here = node.isCurrent;
        return (
          <View
            key={node.step}
            style={[styles.node, here && styles.nodeHere]}
            accessible
            accessibilityLabel={`Step ${node.step}, ${node.name}${here ? ', you are here' : done ? ', done' : ''}`}
          >
            <View style={[styles.nodeDot, done && { backgroundColor: colors.ok }, here && { backgroundColor: colors.orange }]}>
              {done ? <Icon name="check" size={16} color={colors.white} /> : <Text style={[styles.nodeNum, here && { color: colors.white }]}>{node.step}</Text>}
            </View>
            <Text maxFontSizeMultiplier={1.2} style={styles.nodeName}>
              {node.name}
            </Text>
            {here ? (
              <Text maxFontSizeMultiplier={1.2} style={styles.here}>
                You are here
              </Text>
            ) : null}
          </View>
        );
      })}
    </Panel>
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
        <View style={{ width: 74, height: 74, alignItems: 'center', justifyContent: 'center' }}>
          <Svg width={74} height={74} viewBox="0 0 74 74" style={{ position: 'absolute', transform: [{ rotate: '-90deg' }] }}>
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
          {goal.met ? <Icon name="trophy" size={26} color={colors.goldDeep} /> : <Text style={styles.goalNum}>{goal.done}/{goal.target}</Text>}
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
  const MAX = 56;
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
  header: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  name: { fontFamily: fonts.displayMedium, fontSize: 24, lineHeight: 28, color: colors.ink },
  node: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: colors.white, borderRadius: 14, borderWidth: 2, borderColor: colors.line, paddingHorizontal: 10, minHeight: 48 },
  nodeHere: { borderColor: colors.orange, backgroundColor: colors.orangeSoft },
  nodeDot: { width: 28, height: 28, borderRadius: 14, backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  nodeNum: { fontFamily: fonts.uiBold, fontSize: 15, color: colors.blue },
  nodeName: { flex: 1, fontFamily: fonts.uiBold, fontSize: 18, color: colors.ink },
  here: { fontFamily: fonts.uiBold, fontSize: 13, color: colors.orangeDark },
  row: { flexDirection: 'row', gap: 10, alignItems: 'stretch' },
  half: { flex: 1, padding: 10, justifyContent: 'center' },
  goal: { alignItems: 'center', gap: 6 },
  goalNum: { fontFamily: fonts.display, fontSize: 19, color: colors.ink },
  goalText: { fontFamily: fonts.ui, fontSize: 14, lineHeight: 18, color: colors.slate, textAlign: 'center' },
  bars: { flexDirection: 'row', alignItems: 'flex-end', gap: 5 },
  barCol: { flex: 1, alignItems: 'center', gap: 4 },
  bar: { alignSelf: 'stretch', backgroundColor: colors.sky2, borderTopLeftRadius: 6, borderTopRightRadius: 6, borderBottomLeftRadius: 3, borderBottomRightRadius: 3 },
  barLabel: { fontFamily: fonts.ui, fontSize: 12, color: colors.slate },
});
