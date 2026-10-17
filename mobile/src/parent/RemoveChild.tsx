import { useState } from 'react';
import { KeyboardAvoidingView, View } from 'react-native';
import * as api from '../api';
import { Chips, Field } from '../fields';
import { Btn, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { TopBar } from '../teacher/kit';
import { ChildFace } from './kit';
import { space } from '../theme';
import { s } from '../scale';

const SURE = 'I understand this cannot be undone.';

/**
 * A guardian removes a child. Alone as a guardian: the child's profile and every reading record are deleted for good. With another
 * guardian linked: only this parent is removed and the child stays for the other one. The guardian types their own password again, so a
 * child holding a parent's unlocked phone cannot do it.
 *
 * Every real rule is on the server (DataErasureService); this screen only explains it in plain words and shows what the server says.
 */
export function RemoveChild({ child, onBack, onDone }: { child: api.ParentChild; onBack: () => void; onDone: (message: string) => void }) {
  const [password, setPassword] = useState('');
  const [sure, setSure] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const first = child.firstName;
  const shared = (child.otherGuardians ?? 0) > 0;

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
      const result = await api.removeChild(child.id, password);
      setPassword('');
      onDone(result.message);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      setPassword('');
      if (!Object.keys(fields).length) setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen
        withTabBar
        anchor="content"
        header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />}
        footer={<Btn label={shared ? 'Remove me' : `Delete ${first}`} tone="danger" onPress={submit} loading={busy} />}
      >
        <View style={{ gap: space[10], paddingVertical: space[8] }}>
          <TopBar title={shared ? `Remove yourself from ${first}?` : `Delete ${first}'s profile?`} right={<ChildFace child={child} px={s(52)} />} />
          {shared ? (
            <Panel style={{ gap: space[6] }}>
              <P>{first} has another guardian. This only removes you.</P>
              <Small>
                {first}'s profile and reading records stay for them, and you will no longer see {first} here.
              </Small>
            </Panel>
          ) : (
            <Panel style={{ gap: space[6] }}>
              <P>You are {first}'s only guardian, so this deletes {first}'s profile for good.</P>
              <Small>Every reading, badge, word, alert and game record goes, and so does the sign in. This cannot be undone.</Small>
            </Panel>
          )}

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
          {errors.confirm ? <Small style={{ color: '#b3261e' }}>{errors.confirm}</Small> : null}
          {error ? <Notice text={error} /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}
