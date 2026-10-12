import { useMemo, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { Badge } from './api';
import { Medal } from './Feedback';
import { useSession } from './session';
import { Btn, Busy, H1, H2, Notice, P, Screen, Small } from './ui';
import { colors, fonts, size } from './theme';

/**
 * All the badges, earned ones lit and the rest as locks, so a child can see what is ahead. Grouped by kind. Tap one to
 * read what it is for and how far along the child is.
 */
export function Badges() {
  const { home, homeError, reloadHome } = useSession();
  const [open, setOpen] = useState<Badge | null>(null);

  const groups = useMemo(() => {
    const map = new Map<string, Badge[]>();
    (home?.badges ?? []).forEach((badge) => {
      const list = map.get(badge.category) ?? [];
      list.push(badge);
      map.set(badge.category, list);
    });
    return [...map.entries()];
  }, [home]);

  const earned = (home?.badges ?? []).filter((b) => b.earned).length;
  const total = home?.badges.length ?? 0;

  return (
    <Screen withTabBar>
      <H1>My Badges</H1>
      {home ? <Small>{earned} of {total} earned</Small> : null}

      {!home ? (
        homeError ? (
          <View style={{ gap: 12, marginTop: 16 }}>
            <Notice text={homeError} />
            <Btn label="Try Again" onPress={reloadHome} />
          </View>
        ) : (
          <Busy text="Getting your badges" style={{ marginTop: 20 }} />
        )
      ) : (
        groups.map(([category, badges]) => (
          <View key={category} style={{ marginTop: 18 }}>
            <H2>
              {category} <Text style={styles.count}>{badges.filter((b) => b.earned).length} of {badges.length}</Text>
            </H2>
            <View style={styles.grid}>
              {badges.map((badge) => (
                <Pressable
                  key={badge.code}
                  accessibilityRole="button"
                  accessibilityLabel={`${badge.name}, ${badge.earned ? 'earned' : 'not earned yet'}`}
                  onPress={() => setOpen(badge)}
                  style={({ pressed }) => [styles.cell, pressed && { opacity: 0.7 }]}
                >
                  <Medal icon={badge.icon} off={!badge.earned} />
                  <Text maxFontSizeMultiplier={1.15} numberOfLines={2} style={[styles.name, !badge.earned && { color: colors.slate }]}>
                    {badge.name}
                  </Text>
                </Pressable>
              ))}
            </View>
          </View>
        ))
      )}

      <BadgeSheet badge={open} onClose={() => setOpen(null)} />
    </Screen>
  );
}

function BadgeSheet({ badge, onClose }: { badge: Badge | null; onClose: () => void }) {
  const insets = useSafeAreaInsets();
  const progress =
    badge && !badge.earned && badge.target ? `${Math.min(badge.current ?? 0, badge.target)} of ${badge.target} so far` : null;
  const when = badge?.earned && badge.earnedAt ? new Date(badge.earnedAt).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' }) : null;

  return (
    <Modal visible={!!badge} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={styles.backdrop} onPress={onClose} accessibilityLabel="Close" />
      {badge ? (
        <View style={[styles.sheet, { paddingBottom: Math.max(insets.bottom, 16) + 8 }]}>
          <Medal icon={badge.icon} off={!badge.earned} size={92} />
          <H2 style={{ textAlign: 'center' }}>{badge.name}</H2>
          <P style={{ textAlign: 'center' }}>{badge.description}</P>
          {when ? <Small style={{ color: colors.ok, fontFamily: fonts.uiBold }}>Earned on {when}</Small> : null}
          {!badge.earned ? <Small style={{ fontFamily: fonts.uiBold }}>{progress ?? 'Not earned yet. Keep reading!'}</Small> : null}
          <Btn label="Close" tone="ghost" onPress={onClose} style={{ alignSelf: 'stretch' }} />
        </View>
      ) : null}
    </Modal>
  );
}

const styles = StyleSheet.create({
  count: { fontFamily: fonts.ui, fontSize: 15, color: colors.slate },
  grid: { flexDirection: 'row', flexWrap: 'wrap', marginTop: 8, rowGap: 14 },
  cell: { width: '33.333%', alignItems: 'center', gap: 4, paddingHorizontal: 4, minHeight: 48 },
  name: { fontFamily: fonts.ui, fontSize: 14, lineHeight: 17, color: colors.ink, textAlign: 'center' },
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
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    padding: 22,
    alignItems: 'center',
    gap: 10,
  },
});
