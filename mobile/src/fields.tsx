import { forwardRef, useState } from 'react';
import { Linking, Pressable, StyleSheet, Text, TextInput, View, type StyleProp, type TextInputProps, type ViewStyle } from 'react-native';
import { Icon, Small } from './ui';
import { colors, fonts, size, space } from './theme';
import { s } from './scale';
import { LEGAL_URLS } from './config';

// Form pieces for the email sign in and sign up screens: a labelled field, a choice between a few options, and a link.
// They follow the website's forms (label above, error under the field) and the phone rules (every tap at least 48dp).

const FONT_CAP = 1.25;

type FieldProps = Pick<
  TextInputProps,
  'keyboardType' | 'autoCapitalize' | 'autoComplete' | 'textContentType' | 'returnKeyType' | 'maxLength' | 'placeholder' | 'onSubmitEditing' | 'editable' | 'multiline'
> & {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  /** The server's (or the form's) message for this field. Turns the border red and shows the words underneath. */
  error?: string | null;
  /** A short line under the field when there is no error. */
  hint?: string;
  /** Hides what is typed, with an eye to show it again. */
  password?: boolean;
  /** A taller box for a few lines of words (a passage, a note). Enter makes a new line. */
  lines?: number;
  style?: StyleProp<ViewStyle>;
};

/** One labelled field. The label sits above it, as on the website, so it stays readable once the phone's keyboard is open. */
export const Field = forwardRef<TextInput, FieldProps>(function Field(
  { label, value, onChangeText, error, hint, password, lines, style, ...input },
  ref,
) {
  const [shown, setShown] = useState(false);
  const [focused, setFocused] = useState(false);

  return (
    <View style={[st.field, style]}>
      <Text maxFontSizeMultiplier={FONT_CAP} style={st.label}>
        {label}
      </Text>
      <View style={[st.box, !!lines && st.boxTall, focused && st.boxFocus, !!error && st.boxBad]}>
        <TextInput
          ref={ref}
          value={value}
          onChangeText={onChangeText}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          secureTextEntry={!!password && !shown}
          autoCorrect={false}
          placeholderTextColor="#9fb0c0"
          accessibilityLabel={label}
          style={[st.input, !!lines && { minHeight: s(24) * lines + space[16], textAlignVertical: 'top' }]}
          multiline={!!lines || input.multiline}
          {...input}
        />
        {password ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={shown ? 'Hide password' : 'Show password'}
            onPress={() => setShown((v) => !v)}
            hitSlop={4}
            style={st.eye}
          >
            <Icon name="eye" size={s(24)} color={shown ? colors.orange : colors.slate} />
          </Pressable>
        ) : null}
      </View>
      {error ? (
        <Small style={{ color: colors.bad }} numberOfLines={3}>
          {error}
        </Small>
      ) : hint ? (
        <Small>{hint}</Small>
      ) : null}
    </View>
  );
});

type Option<K extends string> = { key: K; label: string };

/** Two or three choices side by side, one picked (for example "One grade" or "More than one"). */
export function Segmented<K extends string>({ options, value, onChange }: { options: Option<K>[]; value: K; onChange: (key: K) => void }) {
  return (
    <View style={st.seg} accessibilityRole="radiogroup">
      {options.map((o) => {
        const on = o.key === value;
        return (
          <Pressable
            key={o.key}
            accessibilityRole="radio"
            accessibilityState={{ selected: on }}
            onPress={() => onChange(o.key)}
            style={[st.segItem, on && st.segOn]}
          >
            <Text maxFontSizeMultiplier={FONT_CAP} style={[st.segText, on && { color: colors.ink }]}>
              {o.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

/** Round chips to switch on or off (for example the grades a teacher handles). */
export function Chips({ options, picked, onToggle, label }: { options: string[]; picked: string[]; onToggle: (option: string) => void; label: string }) {
  return (
    <View style={st.chips} accessibilityLabel={label}>
      {options.map((o) => {
        const on = picked.includes(o);
        return (
          <Pressable
            key={o}
            accessibilityRole="checkbox"
            accessibilityState={{ checked: on }}
            onPress={() => onToggle(o)}
            style={[st.chip, on && st.chipOn]}
          >
            <Text maxFontSizeMultiplier={FONT_CAP} style={[st.chipText, on && { color: colors.white }]}>
              {o}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

/** A quiet text link, at least 48dp tall to tap. The bold part is what the person acts on. */
export function TextLink({ lead, label, onPress }: { lead?: string; label: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="link" accessibilityLabel={lead ? `${lead} ${label}` : label} onPress={onPress} style={st.link}>
      <Text maxFontSizeMultiplier={FONT_CAP} style={st.linkText}>
        {lead ? `${lead} ` : ''}
        <Text style={{ fontFamily: fonts.uiBold, color: colors.blueDark }}>{label}</Text>
      </Text>
    </Pressable>
  );
}

/** A piece of the words next to a consent box: plain text, or a link to one of the legal pages (opens in the phone's browser). */
export type ConsentPart = string | { label: string; url: string };

/**
 * One consent box, as on the website: big enough to tap, empty until the person ticks it, the words say exactly what is agreed
 * to and link to the page that explains it, and the message (from the form or the server) shows under it.
 */
export function Consent({ checked, onChange, parts, error }: { checked: boolean; onChange: (next: boolean) => void; parts: ConsentPart[]; error?: string | null }) {
  const words = parts.map((p) => (typeof p === 'string' ? p : p.label)).join('');
  return (
    <View style={st.consentWrap}>
      <Pressable
        accessibilityRole="checkbox"
        accessibilityState={{ checked }}
        accessibilityLabel={words}
        onPress={() => onChange(!checked)}
        style={[st.consent, checked && st.consentOn, !!error && st.consentBad]}
      >
        <View style={[st.consentBox, checked && st.consentBoxOn]}>{checked ? <Icon name="check" size={s(18)} color={colors.white} /> : null}</View>
        <Text maxFontSizeMultiplier={FONT_CAP} style={st.consentText}>
          {parts.map((p, i) =>
            typeof p === 'string' ? (
              p
            ) : (
              <Text key={i} accessibilityRole="link" onPress={() => Linking.openURL(p.url).catch(() => {})} style={st.consentLink}>
                {p.label}
              </Text>
            ),
          )}
        </Text>
      </Pressable>
      {error ? (
        <Small style={{ color: colors.bad }}>{error}</Small>
      ) : null}
    </View>
  );
}

/** A quiet row of links to the legal pages (Privacy, Terms, Cookies, Refunds). */
export function LegalLinks() {
  const items: { label: string; url: string }[] = [
    { label: 'Privacy', url: LEGAL_URLS.privacy },
    { label: 'Terms', url: LEGAL_URLS.terms },
    { label: 'Cookies', url: LEGAL_URLS.cookies },
    { label: 'Refunds', url: LEGAL_URLS.refunds },
  ];
  return (
    <View style={st.legalRow} accessibilityRole="menu">
      {items.map((it) => (
        <Pressable key={it.label} accessibilityRole="link" accessibilityLabel={`${it.label} policy`} onPress={() => Linking.openURL(it.url).catch(() => {})} style={st.legalItem}>
          <Text maxFontSizeMultiplier={FONT_CAP} style={st.legalText}>
            {it.label}
          </Text>
        </Pressable>
      ))}
    </View>
  );
}

const st = StyleSheet.create({
  consentWrap: { alignSelf: 'stretch', gap: space[4] },
  consent: { flexDirection: 'row', alignItems: 'flex-start', gap: space[12], padding: space[12], minHeight: size.touch, borderRadius: s(14), borderWidth: 2, borderColor: colors.line, backgroundColor: colors.white },
  consentOn: { borderColor: colors.blue },
  consentBad: { borderColor: colors.bad, backgroundColor: colors.badSoft },
  consentBox: { width: s(26), height: s(26), marginTop: s(1), borderRadius: s(7), borderWidth: 2, borderColor: colors.slate, backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  consentBoxOn: { backgroundColor: colors.blue, borderColor: colors.blue },
  consentText: { flex: 1, fontFamily: fonts.uiRegular, fontSize: s(15), lineHeight: s(22), color: colors.ink },
  consentLink: { fontFamily: fonts.uiBold, color: colors.blueDark, textDecorationLine: 'underline' },
  legalRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: space[4] },
  legalItem: { minHeight: size.touch, paddingHorizontal: space[12], alignItems: 'center', justifyContent: 'center' },
  legalText: { fontFamily: fonts.uiRegular, fontSize: s(14), color: colors.slate, textDecorationLine: 'underline' },

  field: { alignSelf: 'stretch', gap: space[4] },
  label: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
  box: {
    minHeight: size.field,
    borderRadius: s(14),
    borderWidth: 2,
    borderColor: colors.line,
    backgroundColor: colors.white,
    flexDirection: 'row',
    alignItems: 'center',
  },
  boxTall: { alignItems: 'stretch' },
  boxFocus: { borderColor: colors.blue },
  boxBad: { borderColor: colors.bad },
  input: { flex: 1, fontFamily: fonts.ui, fontSize: s(18), color: colors.ink, paddingHorizontal: space[14], paddingVertical: space[8] },
  eye: { width: size.touch, height: size.touch, alignItems: 'center', justifyContent: 'center' },

  seg: { alignSelf: 'stretch', flexDirection: 'row', backgroundColor: colors.sky, borderRadius: s(14), padding: space[4], gap: space[4] },
  segItem: { flex: 1, minHeight: size.touch, borderRadius: s(11), alignItems: 'center', justifyContent: 'center', paddingHorizontal: space[8] },
  segOn: { backgroundColor: colors.white },
  segText: { fontFamily: fonts.uiBold, fontSize: s(15), color: colors.slate, textAlign: 'center' },

  chips: { alignSelf: 'stretch', flexDirection: 'row', flexWrap: 'wrap', gap: space[8] },
  chip: { minHeight: size.touch, paddingHorizontal: space[16], borderRadius: s(24), backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center' },
  chipOn: { backgroundColor: colors.ink },
  chipText: { fontFamily: fonts.uiBold, fontSize: s(16), color: colors.blueDark },

  link: { minHeight: size.touch, alignItems: 'center', justifyContent: 'center', alignSelf: 'center', paddingHorizontal: space[12] },
  linkText: { fontFamily: fonts.uiRegular, fontSize: s(16), color: colors.slate, textAlign: 'center' },
});
