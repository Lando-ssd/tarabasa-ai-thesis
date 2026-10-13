import { useEffect, useState, type ReactNode, type Ref } from 'react';
import {
  ActivityIndicator,
  Keyboard,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type TextStyle,
  type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import LottieView from 'lottie-react-native';
import { ICON_PATHS } from './iconPaths';
import { colors, fonts, size } from './theme';

// Small building blocks shared by every screen, so spacing, tap sizes and type stay the same everywhere.

// Larger text settings on the phone are respected, but only up to a point, so a layout never breaks.
const FONT_CAP = 1.25;

// ------------------------------------------------------------------ icons

export function Icon({ name, size: px = 24, color = colors.ink }: { name: string; size?: number; color?: string }) {
  return (
    <Svg width={px} height={px} viewBox="0 0 256 256">
      <Path d={ICON_PATHS[name] ?? ICON_PATHS.medal} fill={color} />
    </Svg>
  );
}

// ------------------------------------------------------------------ text

type TextProps = { children: ReactNode; style?: StyleProp<TextStyle>; numberOfLines?: number };

/** The big friendly heading. */
export function H1({ children, style }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} accessibilityRole="header" style={[s.h1, style]}>
      {children}
    </Text>
  );
}

export function H2({ children, style }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} accessibilityRole="header" style={[s.h2, style]}>
      {children}
    </Text>
  );
}

export function P({ children, style, numberOfLines }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} numberOfLines={numberOfLines} style={[s.p, style]}>
      {children}
    </Text>
  );
}

export function Small({ children, style, numberOfLines }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} numberOfLines={numberOfLines} style={[s.small, style]}>
      {children}
    </Text>
  );
}

// ------------------------------------------------------------------ buttons

type BtnProps = {
  label: string;
  onPress: () => void;
  tone?: 'orange' | 'blue' | 'ghost';
  icon?: string;
  disabled?: boolean;
  loading?: boolean;
  /** A smaller button for secondary actions. Still at least 48dp tall. */
  compact?: boolean;
  style?: StyleProp<ViewStyle>;
  accessibilityHint?: string;
};

/** The clay button: a solid colour with a darker lip underneath that "presses in" when tapped. */
export function Btn({ label, onPress, tone = 'orange', icon, disabled, loading, compact, style, accessibilityHint }: BtnProps) {
  const palette =
    tone === 'blue'
      ? { bg: colors.blue, lip: colors.blueDark, text: colors.white }
      : tone === 'ghost'
        ? { bg: colors.white, lip: colors.line, text: colors.ink }
        : { bg: colors.orange, lip: colors.orangeDark, text: colors.white };
  const inactive = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityHint={accessibilityHint}
      accessibilityState={{ disabled: !!inactive, busy: !!loading }}
      onPress={onPress}
      disabled={inactive}
      style={({ pressed }) => [
        s.btn,
        compact && s.btnCompact,
        { backgroundColor: palette.bg, borderBottomColor: palette.lip },
        tone === 'ghost' && { borderWidth: 2, borderColor: colors.line, borderBottomWidth: 5, borderBottomColor: palette.lip },
        pressed && !inactive && s.btnPressed,
        inactive && s.btnOff,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={palette.text} />
      ) : (
        <>
          {icon ? <Icon name={icon} size={compact ? 22 : 26} color={palette.text} /> : null}
          <Text maxFontSizeMultiplier={FONT_CAP} style={[s.btnText, compact && s.btnTextCompact, { color: palette.text }]}>
            {label}
          </Text>
        </>
      )}
    </Pressable>
  );
}

/** A round icon button (at least 48dp), for things like the back arrow and the speaker. */
export function IconBtn({
  icon,
  label,
  onPress,
  tone = 'ghost',
  disabled,
}: {
  icon: string;
  label: string;
  onPress: () => void;
  tone?: 'ghost' | 'orange';
  disabled?: boolean;
}) {
  const orange = tone === 'orange';
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      onPress={onPress}
      disabled={disabled}
      hitSlop={6}
      style={({ pressed }) => [
        s.iconBtn,
        orange ? { backgroundColor: colors.orange, borderBottomColor: colors.orangeDark } : { backgroundColor: colors.white, borderColor: colors.line, borderWidth: 2, borderBottomColor: colors.line },
        pressed && s.btnPressed,
        disabled && s.btnOff,
      ]}
    >
      <Icon name={icon} size={24} color={orange ? colors.white : colors.ink} />
    </Pressable>
  );
}

// ------------------------------------------------------------------ surfaces

/** The light blue panel with a lip, used to group things. */
export function Panel({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[s.panel, style]}>{children}</View>;
}

export function Pill({ icon, text, color = colors.ink, label }: { icon?: string; text: string; color?: string; label?: string }) {
  return (
    <View style={s.pill} accessible accessibilityLabel={label ?? text}>
      {icon ? <Icon name={icon} size={20} color={color} /> : null}
      <Text maxFontSizeMultiplier={FONT_CAP} style={s.pillText}>
        {text}
      </Text>
    </View>
  );
}

/** The child's initial in a circle. The photo from the website is not used (those files do not last on the free host). */
export function Avatar({ name, px = 48 }: { name: string; px?: number }) {
  return (
    <View style={[s.avatar, { width: px, height: px, borderRadius: px / 2 }]} accessible={false}>
      <Text style={[s.avatarText, { fontSize: px * 0.5, lineHeight: px * 0.7 }]}>{(name || '?').trim().charAt(0).toUpperCase()}</Text>
    </View>
  );
}

export function Busy({ text, style }: { text?: string; style?: StyleProp<ViewStyle> }) {
  return (
    <View style={[s.busy, style]} accessibilityLiveRegion="polite">
      <ActivityIndicator size="small" color={colors.orange} />
      {text ? <Small style={{ flexShrink: 1 }}>{text}</Small> : null}
    </View>
  );
}

export function Notice({ text, tone = 'error' }: { text: string; tone?: 'error' | 'info' }) {
  const bad = tone === 'error';
  return (
    <View
      accessibilityRole="alert"
      style={[s.notice, { backgroundColor: bad ? colors.badSoft : colors.sky, borderColor: bad ? '#f3c9c9' : colors.sky2 }]}
    >
      <Icon name={bad ? 'warning-circle' : 'info'} size={22} color={bad ? colors.bad : colors.blueDark} />
      <Text maxFontSizeMultiplier={FONT_CAP} style={[s.noticeText, { color: bad ? colors.bad : colors.ink }]}>
        {text}
      </Text>
    </View>
  );
}

// ------------------------------------------------------------------ animations (the same files the website uses)

const ANIMATIONS = {
  intro: require('../assets/animations/tara-owl-intro.json'),
  head: require('../assets/animations/tara-owl-head.json'),
  bird: require('../assets/animations/owl-bird.json'),
  happy: require('../assets/animations/star-happy.json'),
  sad: require('../assets/animations/star-sad.json'),
};

export function Animation({ name, px }: { name: keyof typeof ANIMATIONS; px: number }) {
  return (
    <View style={{ width: px, height: px }} accessible={false} importantForAccessibility="no-hide-descendants">
      <LottieView source={ANIMATIONS[name]} autoPlay loop style={{ width: px, height: px }} />
    </View>
  );
}

// ------------------------------------------------------------------ the page

type ScreenProps = {
  children: ReactNode;
  /** A fixed area at the bottom for the main action, where the thumb is. */
  footer?: ReactNode;
  scroll?: boolean;
  /** Leave room for the bottom bar (the bar draws its own bottom space). */
  withTabBar?: boolean;
  background?: string;
  contentStyle?: StyleProp<ViewStyle>;
  /** Lets a screen scroll itself (Home brings the child's own step into view). */
  scrollRef?: Ref<ScrollView>;
};

/**
 * One screen. Keeps content clear of the notch, the status bar and the gesture bar, scrolls on a short phone,
 * keeps a readable column on a wide one, and pins the main action to the bottom edge.
 */
export function Screen({ children, footer, scroll = true, withTabBar, background = colors.page, contentStyle, scrollRef }: ScreenProps) {
  const insets = useSafeAreaInsets();
  const bottom = withTabBar ? 0 : Math.max(insets.bottom, 12);
  const body = (
    <View style={[s.column, contentStyle]}>{children}</View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: background, paddingTop: insets.top + 8 }}>
      {scroll ? (
        <ScrollView
          ref={scrollRef}
          style={{ flex: 1 }}
          contentContainerStyle={{ flexGrow: 1, paddingHorizontal: size.gutter, paddingBottom: footer ? 16 : bottom + 20 }}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {body}
        </ScrollView>
      ) : (
        <View style={{ flex: 1, paddingHorizontal: size.gutter }}>{body}</View>
      )}
      {footer ? (
        <View style={[s.footer, { paddingBottom: bottom + 4 }]}>
          <View style={s.column}>{footer}</View>
        </View>
      ) : null}
    </View>
  );
}

/** True while the phone's keyboard is open, so a screen can give the space back to the form. */
export function useKeyboardOpen() {
  const [open, setOpen] = useState(false);
  useEffect(() => {
    const show = Keyboard.addListener('keyboardDidShow', () => setOpen(true));
    const hide = Keyboard.addListener('keyboardDidHide', () => setOpen(false));
    return () => {
      show.remove();
      hide.remove();
    };
  }, []);
  return open;
}

const s = StyleSheet.create({
  h1: { fontFamily: fonts.display, fontSize: 30, lineHeight: 36, color: colors.orange },
  h2: { fontFamily: fonts.displayMedium, fontSize: 22, lineHeight: 28, color: colors.ink },
  p: { fontFamily: fonts.uiRegular, fontSize: 18, lineHeight: 25, color: colors.slate },
  small: { fontFamily: fonts.uiRegular, fontSize: 15, lineHeight: 20, color: colors.slate },

  btn: {
    minHeight: size.button,
    borderRadius: 16,
    borderBottomWidth: 5,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  btnCompact: { minHeight: size.touch, paddingHorizontal: 16, borderBottomWidth: 4 },
  btnPressed: { transform: [{ translateY: 3 }], borderBottomWidth: 2 },
  btnOff: { opacity: 0.55 },
  btnText: { fontFamily: fonts.uiBold, fontSize: 20, lineHeight: 26 },
  btnTextCompact: { fontSize: 17, lineHeight: 22 },
  iconBtn: {
    width: size.touch,
    height: size.touch,
    borderRadius: size.touch / 2,
    alignItems: 'center',
    justifyContent: 'center',
    borderBottomWidth: 4,
  },

  panel: { backgroundColor: colors.sky, borderRadius: 22, borderBottomWidth: 5, borderBottomColor: colors.sky2, padding: 14 },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minHeight: 36,
    backgroundColor: colors.white,
    borderWidth: 2,
    borderColor: colors.line,
    borderRadius: 99,
    paddingHorizontal: 12,
  },
  pillText: { fontFamily: fonts.uiBold, fontSize: 16, color: colors.ink },
  avatar: { backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center', borderWidth: 3, borderColor: colors.white },
  avatarText: { fontFamily: fonts.display, color: colors.blue },
  busy: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  notice: { flexDirection: 'row', alignItems: 'flex-start', gap: 10, borderRadius: 14, borderWidth: 1, padding: 12 },
  noticeText: { flex: 1, fontFamily: fonts.ui, fontSize: 16, lineHeight: 22 },

  column: { width: '100%', maxWidth: size.maxWidth, alignSelf: 'center', flexGrow: 1 },
  footer: { paddingHorizontal: size.gutter, paddingTop: 10, backgroundColor: colors.page },
});
