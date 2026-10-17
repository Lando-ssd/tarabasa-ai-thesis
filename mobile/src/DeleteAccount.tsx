import { useState } from 'react';
import { KeyboardAvoidingView, View } from 'react-native';
import * as api from './api';
import { Chips, Field } from './fields';
import { Loading, TopBar, useLoad } from './teacher/kit';
import { Btn, H2, IconBtn, Notice, P, Panel, Screen, Small } from './ui';
import { colors, space } from './theme';

const SURE = 'I understand this cannot be undone.';

/**
 * A Teacher or Parent deletes their own account. First it says, in plain sentences from the server, exactly what would happen (the
 * children who go with a Parent's account, the classes that go with a Teacher's); then it asks for the password again. A Teacher with
 * learners they released for promotion that nobody has claimed yet cannot delete yet, and the server says why.
 *
 * Every rule is on the server (DataErasureService, the same code the website's Profile page calls).
 */
export function DeleteAccount({ onBack, onDeleted }: { onBack: () => void; onDeleted: (message: string) => void }) {
  const { data, error: loadError, reload } = useLoad(() => api.eraseInfo(), []);
  const [password, setPassword] = useState('');
  const [sure, setSure] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit() {
    if (busy) return;
    const found: Record<string, string> = {};
    if (!password) found.password = 'Type your password, so we know it is you.';
    if (!sure) found.confirm = 'Tick the box to say you understand this cannot be undone.';
    setErrors(found);
    setError(null);
    if (Object.keys(found).length) return;

    setBusy(true);
    try {
      const result = await api.deleteAccount(password);
      onDeleted(result.message);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      setPassword('');
      // `account` is the server's own reason it cannot be done yet; show it as a message, not under a field.
      if (fields.account) setError(fields.account);
      else if (!Object.keys(fields).length) setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  const ready = !!data && !data.blocked;

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen
        withTabBar
        header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />}
        footer={ready ? <Btn label="Delete my account" tone="danger" onPress={submit} loading={busy} /> : undefined}
      >
        <View style={{ gap: space[12], paddingVertical: space[8] }}>
          <TopBar title="Delete my account" sub="This is permanent" />
          {!data ? (
            <Loading error={loadError} onRetry={reload} text="Checking what would be deleted" />
          ) : (
            <>
              <Panel style={{ gap: space[8] }}>
                <H2>Here is exactly what happens</H2>
                {data.lines.map((line, i) => (
                  <P key={i}>{line}</P>
                ))}
              </Panel>
              {data.blocked ? (
                <Notice text={data.blocked} />
              ) : (
                <>
                  <Field label="Your TaraBasa password" value={password} onChangeText={(t) => (setErrors((e) => ({ ...e, password: '' })), setPassword(t))} password autoCapitalize="none" autoComplete="current-password" error={errors.password} hint="So we know it is you." />
                  <Chips
                    label="Please confirm"
                    options={[SURE]}
                    picked={sure ? [SURE] : []}
                    onToggle={() => {
                      setSure((v) => !v);
                      setErrors((e) => ({ ...e, confirm: '' }));
                    }}
                  />
                  {errors.confirm ? <Small style={{ color: colors.bad }}>{errors.confirm}</Small> : null}
                  {error ? <Notice text={error} /> : null}
                </>
              )}
            </>
          )}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}
