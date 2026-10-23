import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Btn, H2, Icon, P, Small } from './ui';
import { colors, fonts, size, space } from './theme';
import { s } from './scale';

export type StatKind = 'streak' | 'points';

/**
 * The card that opens when a child taps the flame or the star at the top of Home: what the number means. The same words as the
 * website's popovers (days in a row with the week, and the 7 Day Streak badge; points and how they are earned).
 */
export function StatSheet({
  kind,
  days,
  week,
  points,
  onClose,
  onViewBadges,
  onStartReading,
}: {
  kind: StatKind | null;
  days: number;
  week: { label: string; done: boolean }[];
  points: number;
  onClose: () => void;
  onViewBadges: () => void;
  onStartReading: () => void;
}) {
  const insets = useSafeAreaInsets();
  const earned = days >= 7;

  return (
    <Modal visible={!!kind} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={styles.backdrop} onPress={onClose} accessibilityLabel="Close" />
      {kind ? (
        <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, 16) + 8 }]}>
          {kind === 'streak' ? (
            <>
              <View style={[styles.badgeCircle, { backgroundColor: colors.gold }]}>
                <Icon name="fire" size={s(40)} color={colors.orange} />
              </View>
              <H2 style={{ textAlign: 'center' }}>{days > 0 ? `${days} day streak` : 'Start a streak'}</H2>
              <P style={{ textAlign: 'center' }}>
                {days > 0
                  ? `You have read on ${days} ${days === 1 ? 'day' : 'days'} in a row. Keep it going!`
                  : 'Read today to start your streak. Read again tomorrow to keep it going!'}
              </P>

              {week.length > 0 ? (
                <View style={styles.week} accessibilityLabel={`This week: ${week.filter((d) => d.done).length} days read`}>
                  {week.map((d, i) => (
                    <View key={`${d.label}-${i}`} style={styles.day}>
                      <Small style={{ fontFamily: fonts.uiBold }}>{d.label}</Small>
                      <View style={[styles.dot, d.done && styles.dotDone]}>{d.done ? <Icon name="check" size={s(16)} color={colors.white} /> : null}</View>
                    </View>
                  ))}
                </View>
              ) : null}

              <View style={styles.goal}>
                <Icon name={earned ? 'medal' : 'lock-simple'} size={s(30)} color={earned ? colors.goldDeep : colors.slate} />
                <View style={{ flex: 1 }}>
                  <Text maxFontSizeMultiplier={1.2} style={styles.goalTitle}>
                    7 Day Streak badge
                  </Text>
                  <Small>{earned ? 'You earned it. Great reading habit!' : 'Read on 7 days in a row to earn it.'}</Small>
                </View>
              </View>

              <Btn label="View Badges" tone="blue" onPress={onViewBadges} style={{ alignSelf: 'stretch' }} />
              <Btn label="Close" tone="ghost" compact onPress={onClose} style={{ alignSelf: 'stretch' }} />
            </>
          ) : (
            <>
              <View style={[styles.badgeCircle, { backgroundColor: colors.gold }]}>
                <Icon name="star" size={s(40)} color={colors.goldDeep} />
              </View>
              <H2 style={{ textAlign: 'center' }}>Points</H2>
              <P style={{ textAlign: 'center' }}>{`You have ${points.toLocaleString()} ${points === 1 ? 'point' : 'points'}`}</P>
              <Small style={{ textAlign: 'center' }}>You earn points every time you finish a reading.</Small>
              <Btn label="Start Reading" onPress={onStartReading} style={{ alignSelf: 'stretch' }} />
              <Btn label="Close" tone="ghost" compact onPress={onClose} style={{ alignSelf: 'stretch' }} />
            </>
          )}
        </View>
      ) : null}
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { ...StyleSheet.absoluteFill, backgroundColor: 'rgba(12,23,34,0.45)' },
  sheet: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    alignSelf: 'center',
    width: '100%',
    maxWidth: size.maxWidth,
    backgroundColor: colors.white,
    borderTopLeftRadius: s(28),
    borderTopRightRadius: s(28),
    padding: space[20],
    alignItems: 'center',
    gap: space[10],
  },
  badgeCircle: { width: s(72), height: s(72), borderRadius: s(36), alignItems: 'center', justifyContent: 'center' },
  week: { flexDirection: 'row', alignSelf: 'stretch', justifyContent: 'space-between', paddingHorizontal: space[4] },
  day: { alignItems: 'center', gap: space[4], minWidth: s(34) },
  dot: { width: s(28), height: s(28), borderRadius: s(14), backgroundColor: colors.sky, borderWidth: 2, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  dotDone: { backgroundColor: colors.ok, borderColor: colors.ok },
  goal: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space[12],
    alignSelf: 'stretch',
    padding: space[12],
    borderRadius: s(16),
    borderWidth: 2,
    borderColor: colors.line,
    backgroundColor: colors.sky,
  },
  goalTitle: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.ink },
});
