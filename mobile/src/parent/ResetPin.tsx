import { useState } from 'react';
import { KeyboardAvoidingView, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Field, Segmented } from '../fields';
import { Btn, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { TopBar } from '../teacher/kit';
import { ChildFace } from './kit';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

type Mode = 'generate' | 'choose';

/**
 * A child forgot their PIN, and a guardian makes a new one. Two ways: the app makes one (shown once, in big numbers to read out), or the
 * guardian chooses one. Either way the guardian types their own password again, so a child holding a parent's unlocked phone cannot do it.
 *
 * Every real rule is on the server (a linked guardian only, three changes a day, no easy PINs, a PIN different from the old one, the old PIN
 * stops at once and the child is signed out): this screen only asks nicely and shows the server's words. The checks here are for speed.
 */
export function ResetPin({ child, onBack, onDone }: { child: api.ParentChild; onBack: () => void; onDone: () => void }) {
  const [mode, setMode] = useState<Mode>('generate');
  const [pin, setPin] = useState('');
  const [again, setAgain] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [made, setMade] = useState<{ pin: string | null; message: string } | null>(null);

  const first = child.firstName;
  const clear = (field: string) => setErrors((e) => ({ ...e, [field]: '' }));

  async function submit() {
    if (busy) return;
    const found: Record<string, string> = {};
    if (mode === 'choose') {
      if (!/^\d{4}$/.test(pin)) found.pin = 'The PIN must be exactly 4 numbers.';
      else if (again !== pin) found.pin_confirmation = 'The two PINs are not the same.';
    }
    if (!password) found.password = 'Type your password, so we know it is you.';
    setErrors(found);
    setError(null);
    if (Object.keys(found).length) return;

    setBusy(true);
    try {
      const result = await api.resetChildPin(child.id, mode === 'choose' ? { mode, password, pin, pin_confirmation: again } : { mode, password });
      // Nothing about the PIN stays in this screen's memory longer than it must.
      setPin('');
      setAgain('');
      setPassword('');
      setMade(result);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      setPassword('');
      if (!Object.keys(fields).length) setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  if (made) {
    const digits = made.pin ? made.pin.split('') : [];
    return (
      <Screen withTabBar anchor="content" footer={<Btn label="Done" onPress={onDone} />}>
        <View style={{ gap: space[12], paddingVertical: space[8] }}>
          <TopBar title={`${first}'s new PIN`} right={<ChildFace child={child} px={s(52)} />} />
          {digits.length ? (
            <View style={st.digits} accessible accessibilityLabel={`New PIN: ${digits.join(' ')}`}>
              {digits.map((d, i) => (
                <View key={i} style={st.digit}>
                  <Text maxFontSizeMultiplier={1.2} style={st.digitText}>
                    {d}
                  </Text>
                </View>
              ))}
            </View>
          ) : null}
          <Notice
            tone="info"
            text={digits.length ? `Tell ${first} now, or write it down. For safety, TaraBasa cannot show this PIN again.` : `Tell ${first} the PIN you chose. TaraBasa cannot show it again.`}
          />
          <Panel style={{ gap: space[6] }}>
            <P>The old PIN does not work any more.</P>
            <P>{first} is signed out on any other device.</P>
            <P>{first} can type the new PIN right away.</P>
          </Panel>
        </View>
      </Screen>
    );
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen
        withTabBar
        anchor="content"
        header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />}
        footer={<Btn label={mode === 'choose' ? 'Save new PIN' : 'Make a new PIN'} onPress={submit} loading={busy} />}
      >
        <View style={{ gap: space[10], paddingVertical: space[8] }}>
          <TopBar title={`New PIN for ${first}`} sub="Forgot the PIN?" right={<ChildFace child={child} px={s(52)} />} />
          <P>Make a new one. The old PIN stops working right away.</P>

          <Segmented<Mode>
            options={[
              { key: 'generate', label: 'Make one for me' },
              { key: 'choose', label: "I'll choose one" },
            ]}
            value={mode}
            onChange={(next) => {
              setMode(next);
              setErrors({});
              setError(null);
            }}
          />
          <Small>{mode === 'generate' ? 'We pick a random PIN and show it once, in big numbers.' : `Four numbers ${first} can remember.`}</Small>

          {mode === 'choose' ? (
            <>
              <Field label="New PIN" value={pin} onChangeText={(t) => (clear('pin'), setPin(t.replace(/\D/g, '').slice(0, 4)))} keyboardType="number-pad" maxLength={4} password error={errors.pin} />
              <Field
                label="Confirm PIN"
                value={again}
                onChangeText={(t) => (clear('pin_confirmation'), setAgain(t.replace(/\D/g, '').slice(0, 4)))}
                keyboardType="number-pad"
                maxLength={4}
                password
                error={errors.pin_confirmation}
                hint={`Not allowed: four of the same number (1111), a run (1234, 4321) or the PIN ${first} has now.`}
              />
            </>
          ) : errors.pin ? (
            <Notice text={errors.pin} />
          ) : null}

          <Field label="Your TaraBasa password" value={password} onChangeText={(t) => (clear('password'), setPassword(t))} password autoCapitalize="none" autoComplete="current-password" error={errors.password} hint="So we know it is you." />

          {error ? <Notice text={error} /> : null}
          <Small>You can make up to 3 new PINs a day for each child. The other guardians are told when a PIN changes.</Small>
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

const st = StyleSheet.create({
  digits: { flexDirection: 'row', gap: space[10], justifyContent: 'center', paddingVertical: space[8] },
  digit: { width: s(58), height: s(74), borderRadius: s(16), backgroundColor: colors.sky, borderWidth: 2, borderColor: colors.sky2, borderBottomWidth: s(5), alignItems: 'center', justifyContent: 'center' },
  digitText: { fontFamily: fonts.display, fontSize: s(40), lineHeight: s(50), color: colors.blueDark },
});
