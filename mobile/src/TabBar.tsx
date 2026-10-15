import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Icon } from './ui';
import { colors, fonts, size, space } from './theme';
import { s } from './scale';

export type TabItem<K extends string> = { key: K; label: string; icon: string; /** A small number on the icon, such as unread alerts. */ badge?: number };

/** The bottom bar: five places, the one you are in marked in orange. Used by the child's app and by the Teacher and Parent apps. */
export function TabBar<K extends string>({ tabs, active, onChange }: { tabs: TabItem<K>[]; active: K; onChange: (tab: K) => void }) {
  const insets = useSafeAreaInsets();
  return (
    <View style={[st.bar, { paddingBottom: insets.bottom }]} accessibilityRole="tablist">
      {tabs.map((t) => {
        const on = t.key === active;
        return (
          <Pressable
            key={t.key}
            accessibilityRole="tab"
            accessibilityLabel={t.badge ? `${t.label}, ${t.badge} new` : t.label}
            accessibilityState={{ selected: on }}
            onPress={() => onChange(t.key)}
            style={({ pressed }) => [st.tab, pressed && { opacity: 0.6 }]}
          >
            <View style={[st.mark, on && st.markOn]} />
            <View>
              <Icon name={t.icon} size={s(28)} color={on ? colors.orange : colors.slate} />
              {t.badge ? (
                <View style={st.badge}>
                  <Text style={st.badgeText}>{t.badge > 9 ? '9+' : t.badge}</Text>
                </View>
              ) : null}
            </View>
            <Text maxFontSizeMultiplier={1.1} style={[st.label, on && { color: colors.orangeDark }]}>
              {t.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const st = StyleSheet.create({
  bar: { flexDirection: 'row', borderTopWidth: 2, borderTopColor: colors.line, backgroundColor: colors.white },
  tab: { flex: 1, height: size.tabBar, alignItems: 'center', justifyContent: 'center', gap: space[2] },
  mark: { position: 'absolute', top: 0, width: s(28), height: s(4), borderBottomLeftRadius: s(4), borderBottomRightRadius: s(4), backgroundColor: 'transparent' },
  markOn: { backgroundColor: colors.orange },
  label: { fontFamily: fonts.uiBold, fontSize: s(13), color: colors.slate },
  badge: {
    position: 'absolute',
    top: -space[4],
    right: -space[8],
    minWidth: s(18),
    height: s(18),
    borderRadius: s(9),
    backgroundColor: colors.bad,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: space[4],
  },
  badgeText: { fontFamily: fonts.uiBold, fontSize: s(11), color: colors.white },
});
