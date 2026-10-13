import { useEffect, useRef, useState } from 'react';
import { KeyboardAvoidingView, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import * as api from './api';
import { CardScanner } from './CardScanner';
import { looksLikeCode, normalizeCode } from './learnerCode';
import { Animation, Btn, Busy, H1, Notice, P, Screen, Small, useKeyboardOpen } from './ui';
import { colors, fonts, size } from './theme';
import { s, SHORT_HEIGHT } from './scale';

type Props = {
  onSignedIn: (result: api.LoginResult) => void;
  /** A note shown above the form, such as "You were signed out". */
  notice?: string | null;
};

/**
 * Sign in. "Scan my card" is the main way in: the printed card holds the learner code, and the child still types the
 * secret PIN. Typing the code is the backup. The main button sits at the bottom of the screen, where the thumb is.
 */
export function SignIn({ onSignedIn, notice }: Props) {
  const [code, setCode] = useState('');
  const [pin, setPin] = useState('');
  const [scanning, setScanning] = useState(false);
  const [busy, setBusy] = useState(false);
  const [slow, setSlow] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const pinInput = useRef<TextInput>(null);
  const keyboardOpen = useKeyboardOpen();

  // The server sleeps when nobody has used it for a while. Wake it now, before the child has finished typing.
  useEffect(() => {
    api.wakeServer();
  }, []);

  // After a few seconds of waiting, say why, so nobody thinks the app has frozen.
  useEffect(() => {
    if (!busy) {
      setSlow(false);
      return;
    }
    const timer = setTimeout(() => setSlow(true), 5000);
    return () => clearTimeout(timer);
  }, [busy]);

  const ready = looksLikeCode(code) && pin.length === 4;

  async function signIn() {
    if (!ready || busy) return;
    setBusy(true);
    setError(null);
    try {
      onSignedIn(await api.login(normalizeCode(code), pin));
    } catch (e) {
      setPin('');
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  function gotCard(scanned: string) {
    setCode(scanned);
    setScanning(false);
    setError(null);
    setTimeout(() => pinInput.current?.focus(), 400);
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen anchor="content" footer={<Btn label="Log in" tone="blue" onPress={signIn} disabled={!ready} loading={busy} />}>
        <View style={styles.body}>
          {keyboardOpen ? null : <Animation name="intro" px={SHORT_HEIGHT ? s(84) : s(140)} />}
          <H1 style={{ textAlign: 'center' }}>Hi there!</H1>
          <P style={{ textAlign: 'center' }}>Tap the button and show your card</P>

          <Btn label="Scan my card" icon="scan" onPress={() => setScanning(true)} style={styles.wide} />

          <Small>or type your code</Small>

          <TextInput
            value={code}
            onChangeText={(t) => setCode(t.toUpperCase())}
            onBlur={() => setCode((c) => (looksLikeCode(c) ? normalizeCode(c) : c))}
            placeholder="TB26-48293"
            placeholderTextColor="#9fb0c0"
            autoCapitalize="characters"
            autoCorrect={false}
            autoComplete="off"
            maxLength={12}
            returnKeyType="next"
            onSubmitEditing={() => pinInput.current?.focus()}
            accessibilityLabel="Learner code"
            style={[styles.field, code.length > 0 && !looksLikeCode(code) && code.length >= 9 && styles.fieldBad]}
          />

          <PinBoxes pin={pin} onChange={setPin} inputRef={pinInput} onDone={signIn} />

          {notice ? <Notice tone="info" text={notice} /> : null}
          {error ? <Notice text={error} /> : null}
          {busy ? (
            <Busy text={slow ? 'TaraBasa is waking up. This can take up to a minute. Thank you for waiting.' : 'Logging in'} />
          ) : null}
        </View>
      </Screen>

      <CardScanner visible={scanning} onClose={() => setScanning(false)} onCard={gotCard} />
    </KeyboardAvoidingView>
  );
}

/** Four big boxes for the secret PIN. One hidden number field sits over them, so a tap anywhere brings up the keypad. */
function PinBoxes({
  pin,
  onChange,
  inputRef,
  onDone,
}: {
  pin: string;
  onChange: (value: string) => void;
  inputRef: React.RefObject<TextInput | null>;
  onDone: () => void;
}) {
  const [focused, setFocused] = useState(false);
  return (
    <View style={styles.pinWrap}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Secret PIN, ${pin.length} of 4 numbers typed`}
        onPress={() => inputRef.current?.focus()}
        style={styles.pinRow}
      >
        {[0, 1, 2, 3].map((i) => (
          <View key={i} style={[styles.pinBox, focused && i === pin.length && styles.pinBoxActive]}>
            <Text style={styles.pinDot}>{i < pin.length ? '•' : ''}</Text>
          </View>
        ))}
      </Pressable>
      <TextInput
        ref={inputRef}
        value={pin}
        onChangeText={(t) => onChange(t.replace(/\D/g, '').slice(0, 4))}
        onFocus={() => setFocused(true)}
        onBlur={() => setFocused(false)}
        keyboardType="number-pad"
        secureTextEntry
        maxLength={4}
        caretHidden
        returnKeyType="done"
        onSubmitEditing={onDone}
        accessibilityLabel="Secret PIN"
        style={styles.pinHidden}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  body: { alignItems: 'center', gap: s(10) },
  wide: { alignSelf: 'stretch' },
  field: {
    alignSelf: 'stretch',
    minHeight: size.field,
    borderRadius: s(14),
    borderWidth: 2,
    borderColor: colors.line,
    backgroundColor: colors.white,
    textAlign: 'center',
    fontFamily: fonts.ui,
    fontSize: s(20),
    letterSpacing: s(1.5),
    color: colors.ink,
    paddingVertical: s(8),
  },
  fieldBad: { borderColor: colors.bad },
  pinWrap: { alignSelf: 'stretch' },
  pinRow: { flexDirection: 'row', gap: s(10), justifyContent: 'center' },
  pinBox: {
    width: s(46),
    height: size.field + 6,
    borderRadius: s(14),
    borderWidth: 2,
    borderColor: colors.line,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pinBoxActive: { borderColor: colors.orange },
  pinDot: { fontFamily: fonts.uiBold, fontSize: s(24), color: colors.ink, lineHeight: s(30) },
  // Invisible, but real: the phone's keypad is attached to this field.
  pinHidden: { position: 'absolute', width: s(1), height: s(1), opacity: 0 },
});
