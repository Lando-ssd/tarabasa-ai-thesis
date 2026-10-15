import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from 'react-native';
import * as api from '../api';
import { Btn, Icon, IconBtn, Notice, P, Small, H1, Busy } from '../ui';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

// Small pieces the Teacher screens share, so a row, a tag or a heading looks the same everywhere.

export type Tone = 'red' | 'blue' | 'green' | 'amber' | 'grey';

const TONES: Record<Tone, { bg: string; fg: string }> = {
  red: { bg: colors.badSoft, fg: colors.bad },
  blue: { bg: colors.sky, fg: colors.blueDark },
  green: { bg: colors.mint, fg: colors.ok },
  amber: { bg: colors.gold, fg: colors.warn },
  grey: { bg: colors.unsureSoft, fg: colors.unsure },
};

/** A small coloured tag ("Needs support", "8 learners"). The words carry the meaning; the colour only helps. */
export function TonePill({ text, tone = 'blue' }: { text: string; tone?: Tone }) {
  const t = TONES[tone];
  return (
    <View style={[st.pill, { backgroundColor: t.bg }]}>
      <Text maxFontSizeMultiplier={1.25} style={[st.pillText, { color: t.fg }]}>
        {text}
      </Text>
    </View>
  );
}

/** The top of a Teacher screen: a back arrow when there is one, the title and a line under it, and anything on the right. */
export function TopBar({ title, sub, onBack, right }: { title: string; sub?: string; onBack?: () => void; right?: ReactNode }) {
  return (
    <View style={st.top}>
      {onBack ? <IconBtn icon="caret-left" label="Back" onPress={onBack} /> : null}
      <View style={{ flex: 1 }}>
        <H1 numberOfLines={2}>{title}</H1>
        {sub ? <Small>{sub}</Small> : null}
      </View>
      {right}
    </View>
  );
}

/** One tappable row: a picture or icon, a title with a line under it, something on the right, and a chevron. */
export function ListRow({
  lead,
  title,
  sub,
  right,
  onPress,
  chevron = true,
  style,
}: {
  lead?: ReactNode;
  title: string;
  sub?: string;
  right?: ReactNode;
  onPress?: () => void;
  chevron?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const body = (
    <>
      {lead}
      <View style={{ flex: 1, gap: space[2] }}>
        <Text maxFontSizeMultiplier={1.25} style={st.rowTitle}>
          {title}
        </Text>
        {sub ? <Small>{sub}</Small> : null}
      </View>
      {right}
      {onPress && chevron ? <Icon name="caret-right" size={s(20)} color={colors.slate} /> : null}
    </>
  );
  if (!onPress) return <View style={[st.row, style]}>{body}</View>;
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={title} onPress={onPress} style={({ pressed }) => [st.row, pressed && { opacity: 0.7 }, style]}>
      {body}
    </Pressable>
  );
}

/** A number with its name under it (Classes, Learners, Free credits). */
export function Tile({ value, label, onPress }: { value: number | string; label: string; onPress?: () => void }) {
  const body = (
    <>
      <Text maxFontSizeMultiplier={1.2} style={st.tileValue}>
        {value}
      </Text>
      <Small style={{ textAlign: 'center' }}>{label}</Small>
    </>
  );
  return onPress ? (
    <Pressable accessibilityRole="button" accessibilityLabel={`${value} ${label}`} onPress={onPress} style={({ pressed }) => [st.tile, pressed && { opacity: 0.7 }]}>
      {body}
    </Pressable>
  ) : (
    <View style={st.tile} accessible accessibilityLabel={`${value} ${label}`}>
      {body}
    </View>
  );
}

/** The child's (or teacher's) initial in a circle, the same picture the website falls back to. */
export function Initial({ name, px = s(44) }: { name: string; px?: number }) {
  return (
    <View style={[st.initial, { width: px, height: px, borderRadius: px / 2 }]} accessible={false}>
      <Text style={[st.initialText, { fontSize: px * 0.46, lineHeight: px * 0.66 }]}>{(name || '?').trim().charAt(0).toUpperCase()}</Text>
    </View>
  );
}

/** A banner for something that needs the teacher: a heading, a line under it and an optional button. */
export function Banner({ tone, icon, title, text, action }: { tone: 'red' | 'amber' | 'blue'; icon: string; title: string; text?: string; action?: ReactNode }) {
  const t = TONES[tone];
  return (
    <View style={[st.banner, { backgroundColor: t.bg }]} accessibilityRole="alert">
      <Icon name={icon} size={s(28)} color={t.fg} />
      <View style={{ flex: 1, gap: space[2] }}>
        <Text maxFontSizeMultiplier={1.25} style={[st.bannerTitle, { color: t.fg }]}>
          {title}
        </Text>
        {text ? (
          <Text maxFontSizeMultiplier={1.25} style={st.bannerText}>
            {text}
          </Text>
        ) : null}
      </View>
      {action}
    </View>
  );
}

/** What a screen is doing while it fetches: a spinner with a plain line, and (when it failed) the reason and a Try Again button. */
export function Loading({ error, onRetry, text = 'Loading' }: { error: string | null; onRetry: () => void; text?: string }) {
  if (error) {
    return (
      <View style={{ gap: space[12], paddingTop: space[20] }}>
        <Notice text={error} />
        <Btn label="Try Again" tone="ghost" onPress={onRetry} />
      </View>
    );
  }
  return (
    <View style={{ paddingTop: space[24], alignItems: 'center' }}>
      <Busy text={text} />
    </View>
  );
}

/**
 * Fetches something when a screen opens and again on demand. Keeps the last answer while a refresh runs, so a screen never
 * blinks empty when you come back to it.
 */
export function useLoad<T>(load: () => Promise<T>, deps: unknown[] = []) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let alive = true;
    load()
      .then((d) => {
        if (alive) {
          setData(d);
          setError(null);
        }
      })
      .catch((e) => {
        if (alive) setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
      });
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick]);

  const reload = useCallback(() => setTick((t) => t + 1), []);
  return { data, error, reload, setData };
}

export function Hint({ children }: { children: ReactNode }) {
  return <P style={{ textAlign: 'center' }}>{children}</P>;
}

const st = StyleSheet.create({
  pill: { alignSelf: 'flex-start', borderRadius: s(99), paddingHorizontal: space[10], paddingVertical: space[4], minHeight: s(26), justifyContent: 'center' },
  pillText: { fontFamily: fonts.uiBold, fontSize: s(13) },
  top: { flexDirection: 'row', alignItems: 'center', gap: space[12], marginBottom: space[12] },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space[12],
    minHeight: s(64),
    backgroundColor: colors.white,
    borderRadius: s(16),
    borderWidth: 2,
    borderColor: colors.line,
    borderBottomWidth: s(4),
    paddingHorizontal: space[12],
    paddingVertical: space[10],
  },
  rowTitle: { fontFamily: fonts.uiBold, fontSize: s(18), lineHeight: s(23), color: colors.ink },
  // Four tiles share one row on a 360 wide phone, so a tile may shrink: no minimum width.
  tile: { flex: 1, minWidth: 0, minHeight: size.touch + s(24), backgroundColor: colors.sky, borderRadius: s(16), alignItems: 'center', justifyContent: 'center', paddingVertical: space[10], paddingHorizontal: space[4], gap: space[2] },
  tileValue: { fontFamily: fonts.display, fontSize: s(28), lineHeight: s(34), color: colors.blueDark },
  initial: { backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  initialText: { fontFamily: fonts.display, color: colors.blue },
  banner: { flexDirection: 'row', alignItems: 'center', gap: space[12], borderRadius: s(18), padding: space[14] },
  bannerTitle: { fontFamily: fonts.uiBold, fontSize: s(18), lineHeight: s(23) },
  bannerText: { fontFamily: fonts.uiRegular, fontSize: s(15), lineHeight: s(20), color: colors.ink },
});
