import type { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import type { NewBadge } from './api';
import { Animation, Btn, H1, Icon, P, Screen } from './ui';
import { colors, fonts } from './theme';

// The after screen the website calls the "feedback scene": the star, a big heading, a few numbers and one button.
// The happy star is for good news AND for a hard reading (a child is never shown a sad face for a low score).
// The sad star is only for "we could not hear you".

export type Stat = { value: string; label: string; icon: string; tone: string };

type Props = {
  mood: 'happy' | 'sad';
  heading: string;
  subline?: string;
  message?: string;
  /** A short line about the child's level ("Level up! You are now a Sentence Reader"). */
  level?: string | null;
  stats?: Stat[];
  badges?: NewBadge[];
  details?: ReactNode;
  buttonLabel: string;
  onButton: () => void;
  second?: { label: string; onPress: () => void };
};

export function Feedback({ mood, heading, subline, message, level, stats, badges, details, buttonLabel, onButton, second }: Props) {
  const sad = mood === 'sad';
  return (
    <Screen
      footer={
        <View style={{ gap: 10 }}>
          <Btn label={buttonLabel} onPress={onButton} />
          {second ? <Btn label={second.label} tone="ghost" compact onPress={second.onPress} /> : null}
        </View>
      }
    >
      <View style={styles.body}>
        <Animation name={sad ? 'sad' : 'happy'} px={sad ? 150 : 140} />
        <H1 style={[styles.heading, sad && { color: colors.blue }]}>{heading}</H1>
        {subline ? <P style={styles.center}>{subline}</P> : null}
        {message ? <P style={[styles.center, { color: colors.ink }]}>{message}</P> : null}

        {level ? (
          <View style={styles.level} accessibilityLiveRegion="polite">
            <Icon name="trend-up" size={22} color={colors.blueDark} />
            <Text maxFontSizeMultiplier={1.2} style={styles.levelText}>
              {level}
            </Text>
          </View>
        ) : null}

        {stats && stats.length > 0 ? (
          <View style={styles.stats}>
            {stats.map((stat) => (
              <View key={stat.label} style={styles.stat} accessible accessibilityLabel={`${stat.label}: ${stat.value}`}>
                <Icon name={stat.icon} size={22} color={stat.tone} />
                <Text maxFontSizeMultiplier={1.15} style={[styles.statValue, { color: stat.tone }]}>
                  {stat.value}
                </Text>
                <Text maxFontSizeMultiplier={1.15} style={styles.statLabel}>
                  {stat.label}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {badges && badges.length > 0 ? (
          <View style={styles.earned}>
            <Text maxFontSizeMultiplier={1.2} style={styles.earnedTitle}>
              {badges.length === 1 ? 'You earned a badge!' : 'You earned new badges!'}
            </Text>
            <View style={styles.earnedRow}>
              {badges.map((badge) => (
                <View key={badge.code} style={styles.earnedItem} accessible accessibilityLabel={`Badge: ${badge.name}`}>
                  <Medal icon={badge.icon} size={64} />
                  <Text maxFontSizeMultiplier={1.15} style={styles.earnedName}>
                    {badge.name}
                  </Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {details ? <View style={{ alignSelf: 'stretch' }}>{details}</View> : null}
      </View>
    </Screen>
  );
}

/** A round badge. Gold when earned, a quiet grey lock-coloured disc when not yet. */
export function Medal({ icon, size: px = 62, off }: { icon: string; size?: number; off?: boolean }) {
  return (
    <View
      accessible={false}
      style={[
        styles.medal,
        { width: px, height: px, borderRadius: px / 2 },
        off ? { backgroundColor: '#e4eaf0', borderBottomColor: '#cdd7e0' } : { backgroundColor: '#f6b93b', borderBottomColor: colors.goldDeep },
      ]}
    >
      <Icon name={off ? 'lock-simple' : icon} size={px * 0.5} color={off ? '#9aa9b7' : colors.white} />
    </View>
  );
}

const styles = StyleSheet.create({
  body: { alignItems: 'center', gap: 10, paddingBottom: 8 },
  heading: { textAlign: 'center' },
  center: { textAlign: 'center' },
  level: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: colors.sky, borderRadius: 14, paddingVertical: 10, paddingHorizontal: 14, alignSelf: 'stretch', justifyContent: 'center' },
  levelText: { fontFamily: fonts.uiBold, fontSize: 18, lineHeight: 23, color: colors.ink, flexShrink: 1, textAlign: 'center' },
  stats: { flexDirection: 'row', gap: 10, alignSelf: 'stretch' },
  stat: { flex: 1, backgroundColor: colors.white, borderWidth: 2, borderColor: colors.line, borderRadius: 16, paddingVertical: 10, paddingHorizontal: 6, alignItems: 'center', gap: 2 },
  statValue: { fontFamily: fonts.display, fontSize: 26, lineHeight: 32 },
  statLabel: { fontFamily: fonts.ui, fontSize: 14, lineHeight: 18, color: colors.slate, textAlign: 'center' },
  earned: { alignSelf: 'stretch', backgroundColor: colors.gold, borderRadius: 18, padding: 14, gap: 10, alignItems: 'center' },
  earnedTitle: { fontFamily: fonts.displayMedium, fontSize: 20, lineHeight: 26, color: colors.ink },
  earnedRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 14, justifyContent: 'center' },
  earnedItem: { width: 96, alignItems: 'center', gap: 4 },
  earnedName: { fontFamily: fonts.ui, fontSize: 14, lineHeight: 17, color: colors.ink, textAlign: 'center' },
  medal: { alignItems: 'center', justifyContent: 'center', borderBottomWidth: 4 },
});
