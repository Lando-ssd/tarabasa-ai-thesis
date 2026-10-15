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
import { colors, fonts, size, space } from './theme';
import { s } from './scale';

// Small building blocks shared by every screen, so spacing, tap sizes and type stay the same everywhere.

// Larger text settings on the phone are respected, but only up to a point, so a layout never breaks.
const FONT_CAP = 1.25;

// ------------------------------------------------------------------ icons

export function Icon({ name, size: px = s(24), color = colors.ink }: { name: string; size?: number; color?: string }) {
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
    <Text maxFontSizeMultiplier={FONT_CAP} accessibilityRole="header" style={[st.h1, style]}>
      {children}
    </Text>
  );
}

export function H2({ children, style }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} accessibilityRole="header" style={[st.h2, style]}>
      {children}
    </Text>
  );
}

export function P({ children, style, numberOfLines }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} numberOfLines={numberOfLines} style={[st.p, style]}>
      {children}
    </Text>
  );
}

export function Small({ children, style, numberOfLines }: TextProps) {
  return (
    <Text maxFontSizeMultiplier={FONT_CAP} numberOfLines={numberOfLines} style={[st.small, style]}>
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
        st.btn,
        compact && st.btnCompact,
        { backgroundColor: palette.bg, borderBottomColor: palette.lip },
        tone === 'ghost' && { borderWidth: 2, borderColor: colors.line, borderBottomWidth: s(5), borderBottomColor: palette.lip },
        pressed && !inactive && st.btnPressed,
        inactive && st.btnOff,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={palette.text} />
      ) : (
        <>
          {icon ? <Icon name={icon} size={s(compact ? 22 : 26)} color={palette.text} /> : null}
          <Text maxFontSizeMultiplier={FONT_CAP} style={[st.btnText, compact && st.btnTextCompact, { color: palette.text }]}>
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
        st.iconBtn,
        orange ? { backgroundColor: colors.orange, borderBottomColor: colors.orangeDark } : { backgroundColor: colors.white, borderColor: colors.line, borderWidth: 2, borderBottomColor: colors.line },
        pressed && st.btnPressed,
        disabled && st.btnOff,
      ]}
    >
      <Icon name={icon} size={s(24)} color={orange ? colors.white : colors.ink} />
    </Pressable>
  );
}

// ------------------------------------------------------------------ surfaces

/** The light blue panel with a lip, used to group things. */
export function Panel({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[st.panel, style]}>{children}</View>;
}

export function Pill({ icon, text, color = colors.ink, label }: { icon?: string; text: string; color?: string; label?: string }) {
  return (
    <View style={st.pill} accessible accessibilityLabel={label ?? text}>
      {icon ? <Icon name={icon} size={s(20)} color={color} /> : null}
      <Text maxFontSizeMultiplier={FONT_CAP} style={st.pillText}>
        {text}
      </Text>
    </View>
  );
}

/** The child's initial in a circle. The photo from the website is not used (those files do not last on the free host). */
export function Avatar({ name, px = s(48) }: { name: string; px?: number }) {
  return (
    <View style={[st.avatar, { width: px, height: px, borderRadius: px / 2 }]} accessible={false}>
      <Text style={[st.avatarText, { fontSize: px * 0.5, lineHeight: px * 0.7 }]}>{(name || '?').trim().charAt(0).toUpperCase()}</Text>
    </View>
  );
}

export function Busy({ text, style }: { text?: string; style?: StyleProp<ViewStyle> }) {
  return (
    <View style={[st.busy, style]} accessibilityLiveRegion="polite">
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
      style={[st.notice, { backgroundColor: bad ? colors.badSoft : colors.sky, borderColor: bad ? '#f3c9c9' : colors.sky2 }]}
    >
      <Icon name={bad ? 'warning-circle' : 'info'} size={s(22)} color={bad ? colors.bad : colors.blueDark} />
      <Text maxFontSizeMultiplier={FONT_CAP} style={[st.noticeText, { color: bad ? colors.bad : colors.ink }]}>
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
  /** The main action. Where it sits depends on `anchor`. */
  footer?: ReactNode;
  /**
   * "bottom" (the default) pins the footer to the bottom edge, where the thumb is: right for screens with a lot above it
   * (reading, results). "content" puts it straight under the content and centres the whole group on the screen: right
   * for short screens (sign in, welcome, great job) where a button far below its words looks lost.
   */
  anchor?: 'bottom' | 'content';
  scroll?: boolean;
  /** Leave room for the bottom bar (the bar draws its own bottom space). */
  withTabBar?: boolean;
  background?: string;
  contentStyle?: StyleProp<ViewStyle>;
  /** Lets a screen scroll itself (Home brings the child's own step into view). */
  scrollRef?: Ref<ScrollView>;
  /** A row that stays at the top while the rest scrolls, such as a back arrow. */
  header?: ReactNode;
};

/**
 * One screen. Keeps content clear of the notch, the status bar and the gesture bar, scrolls on a short phone,
 * keeps a readable column on a wide one, and pins the main action to the bottom edge.
 */
export function Screen({ children, footer, anchor = 'bottom', scroll = true, withTabBar, background = colors.page, contentStyle, scrollRef, header }: ScreenProps) {
  const insets = useSafeAreaInsets();
  const bottom = withTabBar ? 0 : Math.max(insets.bottom, 12);
  // When the story is taller than the space above the pinned button, a thin line on the button's edge shows that more sits above it.
  const [box, setBox] = useState({ view: 0, content: 0 });
  const scrolls = box.content > box.view + 2;
  const together = !!footer && anchor === 'content';
  const body = (
    <View style={[st.column, together && st.together, contentStyle]}>
      {children}
      {together ? <View style={st.togetherFooter}>{footer}</View> : null}
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: background, paddingTop: insets.top + 8 }}>
      {header ? <View style={[st.header, { paddingHorizontal: size.gutter }]}>{header}</View> : null}
      {scroll ? (
        <ScrollView
          ref={scrollRef}
          style={{ flex: 1 }}
          contentContainerStyle={{ flexGrow: 1, paddingHorizontal: size.gutter, paddingBottom: footer && !together ? s(16) : bottom + s(20) }}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          onLayout={(e) => setBox((b) => ({ ...b, view: e.nativeEvent.layout.height }))}
          onContentSizeChange={(_w, h) => setBox((b) => ({ ...b, content: h }))}
        >
          {body}
        </ScrollView>
      ) : (
        <View style={{ flex: 1, paddingHorizontal: size.gutter, paddingBottom: together ? bottom : 0 }}>{body}</View>
      )}
      {footer && !together ? (
        <View style={[st.footer, { paddingBottom: bottom + 4 }, scrolls && st.footerEdge]}>
          <View style={st.column}>{footer}</View>
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

const st = StyleSheet.create({
  h1: { fontFamily: fonts.display, fontSize: s(26), lineHeight: s(31), color: colors.orange },
  h2: { fontFamily: fonts.displayMedium, fontSize: s(20), lineHeight: s(25), color: colors.ink },
  p: { fontFamily: fonts.uiRegular, fontSize: s(16), lineHeight: s(22), color: colors.slate },
  small: { fontFamily: fonts.uiRegular, fontSize: s(14), lineHeight: s(19), color: colors.slate },

  btn: {
    minHeight: size.button,
    borderRadius: s(16),
    borderBottomWidth: s(5),
    paddingHorizontal: space[20],
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: space[10],
  },
  btnCompact: { minHeight: size.touch, paddingHorizontal: space[16], borderBottomWidth: s(4) },
  btnPressed: { transform: [{ translateY: 3 }], borderBottomWidth: s(2) },
  btnOff: { opacity: 0.55 },
  btnText: { fontFamily: fonts.uiBold, fontSize: s(19), lineHeight: s(24) },
  btnTextCompact: { fontSize: s(16), lineHeight: s(20) },
  iconBtn: {
    width: size.touch,
    height: size.touch,
    borderRadius: size.touch / 2,
    alignItems: 'center',
    justifyContent: 'center',
    borderBottomWidth: s(4),
  },

  panel: { backgroundColor: colors.sky, borderRadius: s(22), borderBottomWidth: s(5), borderBottomColor: colors.sky2, padding: space[14] },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space[6],
    minHeight: s(36),
    backgroundColor: colors.white,
    borderWidth: 2,
    borderColor: colors.line,
    borderRadius: s(99),
    paddingHorizontal: space[12],
  },
  pillText: { fontFamily: fonts.uiBold, fontSize: s(14), color: colors.ink },
  avatar: { backgroundColor: colors.sky2, alignItems: 'center', justifyContent: 'center', borderWidth: 3, borderColor: colors.white },
  avatarText: { fontFamily: fonts.display, color: colors.blue },
  busy: { flexDirection: 'row', alignItems: 'center', gap: space[10] },
  notice: { flexDirection: 'row', alignItems: 'flex-start', gap: space[10], borderRadius: s(14), borderWidth: 1, padding: space[12] },
  noticeText: { flex: 1, fontFamily: fonts.ui, fontSize: s(14), lineHeight: s(19) },

  column: { width: '100%', maxWidth: size.maxWidth, alignSelf: 'center', flexGrow: 1 },
  // The group (content and its button) sits in the middle of the screen; the button follows the content closely.
  together: { justifyContent: 'center' },
  header: { width: '100%', maxWidth: size.maxWidth + size.gutter * 2, alignSelf: 'center', flexDirection: 'row', alignItems: 'center' },
  togetherFooter: { marginTop: space[16] },
  footer: { paddingHorizontal: size.gutter, paddingTop: space[10], backgroundColor: colors.page },
  footerEdge: { borderTopWidth: 2, borderTopColor: colors.line },
});
