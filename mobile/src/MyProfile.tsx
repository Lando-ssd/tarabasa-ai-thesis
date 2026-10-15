import { useState, type ReactNode } from 'react';
import { KeyboardAvoidingView, Text, View } from 'react-native';
import * as api from './api';
import { Chips, Field, Segmented } from './fields';
import { TopBar } from './teacher/kit';
import { Btn, H2, Notice, P, Panel, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';

const GRADES = ['Grade 1', 'Grade 2', 'Grade 3'];

type Props = {
  account: api.Account;
  onBack: () => void;
  /** The server's new copy of the account after a change, so the rest of the app shows the new name. */
  onAccount: (account: api.Account) => void;
  /** Leave room for the bottom bar. */
  withTabBar?: boolean;
  /** Something to show at the very end, such as Log out for a Parent (a Teacher's is on the More menu). */
  extra?: ReactNode;
};

/**
 * My profile, for a Teacher or a Parent: name and contact number, password, and (a Teacher) the grades they handle. The email and a
 * Teacher's school and employee ID are shown but not changed here, as on the website: the Admin checked them, so a change goes
 * through the Admin.
 */
export function MyProfile({ account, onBack, onAccount, withTabBar = true, extra }: Props) {
  const teacher = account.role === 'teacher';

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen withTabBar={withTabBar}>
        <TopBar title="My profile" sub={account.email} onBack={onBack} />
        <View style={{ gap: space[16] }}>
          <Details account={account} onAccount={onAccount} />
          {teacher ? <Grades account={account} onAccount={onAccount} /> : null}
          <Password />
          {teacher ? (
            <Panel style={{ gap: space[6] }}>
              <H2>School</H2>
              <P style={{ color: colors.ink, fontFamily: fonts.ui }}>{account.teacher?.schoolName}</P>
              <Small>Your school and employee ID were checked by the Admin, so they cannot be changed here. If something is wrong, ask the Admin.</Small>
            </Panel>
          ) : null}
          {extra}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

function useSaver() {
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  async function run<T extends { message: string }>(work: () => Promise<T>, done?: (result: T) => void) {
    if (busy) return;
    setBusy(true);
    setMessage(null);
    setProblem(null);
    setErrors({});
    try {
      const result = await work();
      setMessage(result.message);
      done?.(result);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }
  return { busy, message, problem, errors, setErrors, run };
}

function Details({ account, onAccount }: { account: api.Account; onAccount: (a: api.Account) => void }) {
  const [first, setFirst] = useState(account.firstName);
  const [mi, setMi] = useState(account.middleInitial ?? '');
  const [last, setLast] = useState(account.lastName);
  const [phone, setPhone] = useState(account.contactNumber ?? '');
  const sv = useSaver();

  const save = () => {
    const found: Record<string, string> = {};
    if (!first.trim()) found.first_name = 'Please type your first name.';
    if (!last.trim()) found.last_name = 'Please type your last name.';
    if (Object.keys(found).length) {
      sv.setErrors(found);
      return;
    }
    sv.run(() => api.updateProfile({ first_name: first.trim(), middle_initial: mi.trim(), last_name: last.trim(), contact_number: phone.trim() }), (r) => onAccount(r.account));
  };

  return (
    <Panel style={{ gap: space[10] }}>
      <H2>Your details</H2>
      <Field label="First name" value={first} onChangeText={setFirst} error={sv.errors.first_name} autoComplete="given-name" />
      <Field label="Middle initial (optional)" value={mi} onChangeText={setMi} error={sv.errors.middle_initial} maxLength={5} autoCapitalize="characters" />
      <Field label="Last name" value={last} onChangeText={setLast} error={sv.errors.last_name} autoComplete="family-name" />
      <Field label="Contact number (optional)" value={phone} onChangeText={setPhone} error={sv.errors.contact_number} keyboardType="phone-pad" maxLength={30} />
      {sv.message ? <Notice tone="info" text={sv.message} /> : null}
      {sv.problem ? <Notice text={sv.problem} /> : null}
      <Btn label="Save details" onPress={save} loading={sv.busy} />
    </Panel>
  );
}

function Grades({ account, onAccount }: { account: api.Account; onAccount: (a: api.Account) => void }) {
  const current = account.teacher?.grades ?? [];
  const [mode, setMode] = useState<'single' | 'multi'>(current.length > 1 ? 'multi' : 'single');
  const [grades, setGrades] = useState<string[]>(current);
  const [local, setLocal] = useState<string | null>(null);
  const sv = useSaver();

  const toggle = (g: string) => {
    setLocal(null);
    setGrades((cur) => (mode === 'single' ? [g] : cur.includes(g) ? cur.filter((x) => x !== g) : [...cur, g].sort()));
  };
  const choose = (m: 'single' | 'multi') => {
    setMode(m);
    setLocal(null);
    setGrades((cur) => (m === 'single' ? cur.slice(0, 1) : cur));
  };

  const save = () => {
    if (mode === 'single' && grades.length !== 1) return setLocal('Pick the one grade you handle.');
    if (mode === 'multi' && grades.length < 2) return setLocal('Tick at least two grades, or choose One grade.');
    sv.run(() => api.updateGrades({ grades_mode: mode, grades_handled: grades }), (r) => onAccount(r.account));
  };

  return (
    <Panel style={{ gap: space[10] }}>
      <H2>Grades you handle</H2>
      <Segmented
        options={[
          { key: 'single', label: 'One grade' },
          { key: 'multi', label: 'More than one' },
        ]}
        value={mode}
        onChange={choose}
      />
      <Chips label="Grades you handle" options={GRADES} picked={grades} onToggle={toggle} />
      {local || sv.errors.grades_handled ? <Small style={{ color: colors.bad }}>{local ?? sv.errors.grades_handled}</Small> : <Small>Every class holds one grade only. With more than one grade you make a separate class for each.</Small>}
      {sv.message ? <Notice tone="info" text={sv.message} /> : null}
      {sv.problem ? <Notice text={sv.problem} /> : null}
      <Btn label="Save grades" onPress={save} loading={sv.busy} />
    </Panel>
  );
}

function Password() {
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [again, setAgain] = useState('');
  const [local, setLocal] = useState<Record<string, string>>({});
  const sv = useSaver();

  const save = () => {
    const found: Record<string, string> = {};
    if (!current) found.current_password = 'Type your current password.';
    if (next.length < 8) found.password = 'Use at least 8 characters.';
    else if (next !== again) found.password_confirmation = 'The two new passwords are not the same.';
    setLocal(found);
    if (Object.keys(found).length) return;
    sv.run(
      () => api.changePassword({ current_password: current, password: next, password_confirmation: again }),
      () => {
        setCurrent('');
        setNext('');
        setAgain('');
      },
    );
  };

  const err = (k: string) => local[k] || sv.errors[k] || (k === 'password' ? sv.errors.password_confirmation : undefined);

  return (
    <Panel style={{ gap: space[10] }}>
      <H2>Change your password</H2>
      <Field label="Current password" value={current} onChangeText={setCurrent} password error={err('current_password')} autoComplete="current-password" />
      <Field label="New password" value={next} onChangeText={setNext} password error={err('password')} hint="At least 8 characters." autoComplete="new-password" />
      <Field label="New password again" value={again} onChangeText={setAgain} password error={local.password_confirmation} autoComplete="new-password" />
      {sv.message ? <Notice tone="info" text={sv.message} /> : null}
      {sv.problem ? <Notice text={sv.problem} /> : null}
      <Btn label="Change password" onPress={save} loading={sv.busy} />
    </Panel>
  );
}
