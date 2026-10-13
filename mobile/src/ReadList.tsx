import { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { Animation, Btn, Busy, H1, Icon, Notice, P, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';
import { s } from './scale';

/**
 * What should I read? The stories the teacher gave, plus one the app picked from the child's own reading. A story that
 * is too long for the child right now is shown last and marked, never hidden.
 */
export function ReadList({ onOpen }: { onOpen: (id: number) => void }) {
  const [options, setOptions] = useState<api.ActivityOption[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setOptions((await api.activities()).options);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }, []);

  useEffect(() => {
    load();
    // Wake the scoring service now, so the first recording is not slow.
    api.warm().catch(() => {});
  }, [load]);

  return (
    <Screen withTabBar>
      <H1>What should I read?</H1>

      {error ? (
        <View style={{ gap: space[12], marginTop: space[16] }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={load} />
        </View>
      ) : !options ? (
        <Busy text="Finding your stories" style={{ marginTop: space[20] }} />
      ) : options.length === 0 ? (
        <View style={styles.empty}>
          <Animation name="head" px={s(130)} />
          <P style={{ textAlign: 'center' }}>Your teacher has not given you a story yet. Ask your teacher or a grown up, then come back.</P>
          <Btn label="Look Again" tone="ghost" compact onPress={load} />
        </View>
      ) : (
        <View style={{ gap: space[12], marginTop: space[14] }}>
          {options.map((option) => (
            <Story key={option.id} option={option} onPress={() => onOpen(option.id)} />
          ))}
        </View>
      )}
    </Screen>
  );
}

function Story({ option, onPress }: { option: api.ActivityOption; onPress: () => void }) {
  const picked = option.isRecommended;
  const later = option.later;
  const label = later ? 'A bigger one for later' : option.source;
  const icon = picked ? 'star' : later ? 'lock-simple' : option.source === 'Extra Practice' ? 'sparkle' : 'book-open';
  const tone = picked ? colors.orange : later ? colors.slate : colors.blue;
  const meta = [option.wordCount ? `${option.wordCount} words` : null, option.practice].filter(Boolean).join('. ');

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${label}. ${option.title}. ${meta}`}
      onPress={onPress}
      style={({ pressed }) => [styles.card, picked && styles.cardPicked, later && { opacity: 0.8 }, pressed && { transform: [{ translateY: 2 }] }]}
    >
      <View style={styles.cover}>
        <Icon name={icon} size={s(30)} color={tone} />
      </View>
      <View style={{ flex: 1, gap: space[2] }}>
        <Text maxFontSizeMultiplier={1.15} style={[styles.label, { color: picked ? colors.orangeDark : tone }]}>
          {label}
        </Text>
        <Text maxFontSizeMultiplier={1.2} style={styles.title}>
          {option.title}
        </Text>
        {meta ? <Small>{meta}</Small> : null}
      </View>
      <Icon name="caret-right" size={s(22)} color={colors.slate} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  empty: { alignItems: 'center', gap: space[12], marginTop: space[24] },
  card: { flexDirection: 'row', alignItems: 'center', gap: space[12], minHeight: s(84), backgroundColor: colors.white, borderWidth: 2, borderColor: colors.line, borderRadius: s(18), padding: space[12] },
  cardPicked: { borderColor: colors.orange, backgroundColor: colors.orangeSoft },
  cover: { width: s(52), height: s(60), borderRadius: s(12), backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  label: { fontFamily: fonts.uiBold, fontSize: s(12.5), letterSpacing: s(0.5), textTransform: 'uppercase' },
  title: { fontFamily: fonts.uiBold, fontSize: s(21), lineHeight: s(24), color: colors.ink },
});
