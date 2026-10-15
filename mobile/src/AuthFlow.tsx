import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { BackHandler, KeyboardAvoidingView, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import * as api from './api';
import { Chips, Field, Segmented, TextLink } from './fields';
import { SignIn } from './SignIn';
import { Btn, Busy, H1, H2, Icon, IconBtn, Notice, P, Screen, Small } from './ui';
import { colors, fonts, space } from './theme';
import { s } from './scale';

type Step = 'learner' | 'email' | 'create' | 'parent' | 'teacher' | 'check' | 'forgot';

type Sent = { email: string; masked: string; role: 'teacher' | 'parent' };

type Props = {
  /** Which screen to open on. A child who logged out starts on their own screen, a Teacher or Parent on the email screen. */
  start?: 'learner' | 'email';
  /** A note above the first screen, such as "Please log in again." */
  notice?: string | null;
  onLearner: (result: api.LoginResult) => void;
  onAccount: (login: api.AccountLogin) => void;
};

const GRADES = ['Grade 1', 'Grade 2', 'Grade 3'];

/**
 * Everything before sign in: the child's own screen (code and PIN) and, one quiet line below it, the email screens for
 * Teachers and Parents (sign in, create an account, check your email, forgot password). The rules are the website's: the
 * server applies them, this only asks for the details and shows the server's own words.
 */
export function AuthFlow({ start = 'learner', notice, onLearner, onAccount }: Props) {
  const [step, setStep] = useState<Step>(start);
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState<Sent | null>(null);

  // The server sleeps when nobody has used it for a while. Wake it now, before anything is typed.
  useEffect(() => {
    api.wakeServer();
  }, []);

  // The phone's back button goes up one screen before it leaves the app.
  const back = useCallback(() => {
    const parent: Partial<Record<Step, Step>> = { email: 'learner', create: 'email', parent: 'create', teacher: 'create', check: 'email', forgot: 'email' };
    const to = parent[step];
    if (!to) return false;
    setStep(to);
    return true;
  }, [step]);

  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', back);
    return () => sub.remove();
  }, [back]);

  const registered = (r: api.Registered) => {
    if (r.status === 'signed_in') {
      onAccount({ token: r.token, account: r.account });
      return;
    }
    setEmail(r.email);
    setSent({ email: r.email, masked: r.maskedEmail, role: r.role });
    setStep('check');
  };

  switch (step) {
    case 'learner':
      return <SignIn notice={notice} onSignedIn={onLearner} onEmail={() => setStep('email')} />;
    case 'email':
      return (
        <EmailSignIn
          email={email}
          onEmail={setEmail}
          notice={notice}
          onBack={() => setStep('learner')}
          onForgot={() => setStep('forgot')}
          onCreate={() => setStep('create')}
          onDone={onAccount}
        />
      );
    case 'create':
      return <CreateAccount onBack={() => setStep('email')} onPick={(role) => setStep(role)} />;
    case 'parent':
      return <ParentForm onBack={() => setStep('create')} onRegistered={registered} />;
    case 'teacher':
      return <TeacherForm onBack={() => setStep('create')} onRegistered={registered} />;
    case 'check':
      return sent ? <CheckEmail sent={sent} onLogin={() => setStep('email')} /> : null;
    case 'forgot':
      return <Forgot email={email} onEmail={setEmail} onBack={() => setStep('email')} />;
  }
}

// ------------------------------------------------------------------ shared bits

function BackRow({ onBack }: { onBack: () => void }) {
  return <IconBtn icon="caret-left" label="Back" onPress={onBack} />;
}

/** One form screen: a back arrow that stays put, a centered heading, the fields, and the main button right under them. */
function FormScreen({
  title,
  sub,
  onBack,
  footer,
  children,
}: {
  title: string;
  sub?: string;
  onBack?: () => void;
  footer: ReactNode;
  children: ReactNode;
}) {
  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen anchor="content" header={onBack ? <BackRow onBack={onBack} /> : undefined} footer={footer}>
        <View style={st.body}>
          <H1 style={{ textAlign: 'center' }}>{title}</H1>
          {sub ? <P style={{ textAlign: 'center' }}>{sub}</P> : null}
          {children}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

/** After a few seconds of waiting, say why, so nobody thinks the app has frozen. */
function useSlow(busy: boolean) {
  const [slow, setSlow] = useState(false);
  useEffect(() => {
    if (!busy) {
      setSlow(false);
      return;
    }
    const timer = setTimeout(() => setSlow(true), 5000);
    return () => clearTimeout(timer);
  }, [busy]);
  return slow;
}

const WAKING = 'TaraBasa is waking up. This can take up to a minute. Thank you for waiting.';

function messageOf(e: unknown) {
  return e instanceof Error ? e.message : 'Something went wrong. Please try again.';
}

// ------------------------------------------------------------------ email sign in

function EmailSignIn({
  email,
  onEmail,
  notice,
  onBack,
  onForgot,
  onCreate,
  onDone,
}: {
  email: string;
  onEmail: (value: string) => void;
  notice?: string | null;
  onBack: () => void;
  onForgot: () => void;
  onCreate: () => void;
  onDone: (login: api.AccountLogin) => void;
}) {
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Set when the password was right but the email was never confirmed: the screen then offers a new link.
  const [unconfirmed, setUnconfirmed] = useState<string | null>(null);
  const [resent, setResent] = useState<string | null>(null);
  const [resending, setResending] = useState(false);
  const passwordInput = useRef<TextInput>(null);
  const slow = useSlow(busy);

  const ready = email.trim().length > 3 && password.length > 0;

  async function submit() {
    if (!ready || busy) return;
    setBusy(true);
    setError(null);
    setUnconfirmed(null);
    setResent(null);
    try {
      onDone(await api.accountLogin(email, password));
    } catch (e) {
      setPassword('');
      const refusal = api.accountRefusal(e);
      if (refusal.reason === 'email_not_confirmed') setUnconfirmed(refusal.email ?? email.trim());
      setError(messageOf(e));
    } finally {
      setBusy(false);
    }
  }

  async function resend() {
    if (!unconfirmed || resending) return;
    setResending(true);
    try {
      setResent((await api.resendVerification(unconfirmed)).message);
    } catch (e) {
      setResent(messageOf(e));
    } finally {
      setResending(false);
    }
  }

  return (
    <FormScreen
      title="Welcome back"
      sub="Log in with your email and password."
      onBack={onBack}
      footer={
        <View style={st.footerStack}>
          <Btn label="Log in" onPress={submit} disabled={!ready} loading={busy} />
          <TextLink label="Forgot password?" onPress={onForgot} />
          <Btn label="Create an account" tone="ghost" onPress={onCreate} />
          <TextLink label="I am a learner" onPress={onBack} />
        </View>
      }
    >
      <Field
        label="Email"
        value={email}
        onChangeText={onEmail}
        placeholder="you@email.com"
        keyboardType="email-address"
        autoCapitalize="none"
        autoComplete="email"
        textContentType="emailAddress"
        returnKeyType="next"
        onSubmitEditing={() => passwordInput.current?.focus()}
      />
      <Field
        ref={passwordInput}
        label="Password"
        value={password}
        onChangeText={setPassword}
        placeholder="Your password"
        password
        autoCapitalize="none"
        autoComplete="current-password"
        textContentType="password"
        returnKeyType="done"
        onSubmitEditing={submit}
      />
      {notice && !error ? <Notice tone="info" text={notice} /> : null}
      {error ? <Notice tone={unconfirmed ? 'info' : 'error'} text={error} /> : null}
      {unconfirmed ? <Btn label="Send me a new link" tone="blue" icon="paper-plane-tilt" onPress={resend} loading={resending} /> : null}
      {resent ? <Notice tone="info" text={resent} /> : null}
      {busy ? <Busy text={slow ? WAKING : 'Logging in'} /> : null}
    </FormScreen>
  );
}

// ------------------------------------------------------------------ create an account

function CreateAccount({ onBack, onPick }: { onBack: () => void; onPick: (role: 'parent' | 'teacher') => void }) {
  return (
    <Screen anchor="content" header={<BackRow onBack={onBack} />}>
      <View style={st.body}>
        <H1 style={{ textAlign: 'center' }}>Create an account</H1>
        <P style={{ textAlign: 'center' }}>Who are you?</P>
        <RoleRow icon="heart" title="I am a parent" text="Add my child, follow their reading, unlock activities" onPress={() => onPick('parent')} />
        <RoleRow icon="chalkboard-teacher" title="I am a teacher" text="Make classes, assign activities, see progress" onPress={() => onPick('teacher')} />
        <Small style={{ textAlign: 'center' }}>Children do not sign up. A parent adds them, and they get a card with a code.</Small>
      </View>
    </Screen>
  );
}

function RoleRow({ icon, title, text, onPress }: { icon: string; title: string; text: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={title} accessibilityHint={text} onPress={onPress} style={({ pressed }) => [st.role, pressed && { opacity: 0.7 }]}>
      <View style={st.roleIcon}>
        <Icon name={icon} size={s(28)} color={colors.blueDark} />
      </View>
      <View style={{ flex: 1, gap: space[2] }}>
        <H2>{title}</H2>
        <Small>{text}</Small>
      </View>
      <Icon name="caret-right" size={s(22)} color={colors.slate} />
    </Pressable>
  );
}

// ------------------------------------------------------------------ sign up (Parent and Teacher)

type FormState = {
  first_name: string;
  middle_initial: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

const EMPTY: FormState = { first_name: '', middle_initial: '', last_name: '', email: '', password: '', password_confirmation: '' };

/** What both sign ups ask for: the name, the email and the password twice. Checks the easy things before asking the server. */
function usePersonFields() {
  const [form, setForm] = useState<FormState>(EMPTY);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const set = (key: keyof FormState) => (value: string) => {
    setForm((f) => ({ ...f, [key]: value }));
    if (errors[key]) setErrors((e) => ({ ...e, [key]: '' }));
  };
  const check = () => {
    const found: Record<string, string> = {};
    if (!form.first_name.trim()) found.first_name = 'Please type your first name.';
    if (!form.last_name.trim()) found.last_name = 'Please type your last name.';
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) found.email = 'Please type your email address.';
    if (form.password.length < 8) found.password = 'Use at least 8 characters.';
    else if (form.password !== form.password_confirmation) found.password_confirmation = 'The two passwords are not the same.';
    return found;
  };
  return { form, errors, setErrors, set, check };
}

function PersonFields({ p }: { p: ReturnType<typeof usePersonFields> }) {
  const { form, errors, set } = p;
  return (
    <>
      <Field label="First name" value={form.first_name} onChangeText={set('first_name')} error={errors.first_name} autoComplete="given-name" textContentType="givenName" />
      <View style={st.pair}>
        <Field label="Middle initial" value={form.middle_initial} onChangeText={(t) => set('middle_initial')(t.slice(0, 5))} error={errors.middle_initial} autoCapitalize="characters" style={{ flex: 1 }} />
        <Field label="Last name" value={form.last_name} onChangeText={set('last_name')} error={errors.last_name} autoComplete="family-name" textContentType="familyName" style={{ flex: 1.4 }} />
      </View>
      <Field
        label="Email"
        value={form.email}
        onChangeText={set('email')}
        error={errors.email}
        placeholder="you@email.com"
        keyboardType="email-address"
        autoCapitalize="none"
        autoComplete="email"
        textContentType="emailAddress"
      />
    </>
  );
}

function PasswordFields({ p }: { p: ReturnType<typeof usePersonFields> }) {
  const { form, errors, set } = p;
  return (
    <>
      <Field label="Password" value={form.password} onChangeText={set('password')} error={errors.password} hint="At least 8 characters." password autoCapitalize="none" autoComplete="new-password" textContentType="newPassword" />
      <Field label="Type it again" value={form.password_confirmation} onChangeText={set('password_confirmation')} error={errors.password_confirmation} password autoCapitalize="none" autoComplete="new-password" textContentType="newPassword" />
    </>
  );
}

function ParentForm({ onBack, onRegistered }: { onBack: () => void; onRegistered: (r: api.Registered) => void }) {
  const p = usePersonFields();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const slow = useSlow(busy);

  async function submit() {
    if (busy) return;
    const found = p.check();
    if (Object.keys(found).length) {
      p.setErrors(found);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      onRegistered(await api.registerParent({ ...p.form, email: p.form.email.trim().toLowerCase() }));
    } catch (e) {
      const fields = api.fieldErrors(e);
      p.setErrors(fields);
      // A message that belongs to a field is shown at that field. Anything else is shown on its own.
      if (!Object.keys(fields).length) setError(messageOf(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <FormScreen title="Parent sign up" onBack={onBack} footer={<Btn label="Create my account" onPress={submit} loading={busy} />}>
      <PersonFields p={p} />
      <PasswordFields p={p} />
      {error ? <Notice text={error} /> : null}
      {busy ? <Busy text={slow ? WAKING : 'Creating your account'} /> : null}
    </FormScreen>
  );
}

function TeacherForm({ onBack, onRegistered }: { onBack: () => void; onRegistered: (r: api.Registered) => void }) {
  const p = usePersonFields();
  const [school, setSchool] = useState('');
  const [employeeId, setEmployeeId] = useState('');
  const [mode, setMode] = useState<'single' | 'multi'>('single');
  const [grades, setGrades] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const slow = useSlow(busy);

  const gradesChanged = () => p.setErrors((e) => ({ ...e, grades_handled: '' }));
  const chooseMode = (next: 'single' | 'multi') => {
    setMode(next);
    gradesChanged();
    // One grade means exactly one: keep the first pick when going back to it.
    if (next === 'single') setGrades((g) => g.slice(0, 1));
  };
  const toggle = (grade: string) => {
    gradesChanged();
    setGrades((g) => (mode === 'single' ? [grade] : g.includes(grade) ? g.filter((x) => x !== grade) : [...g, grade].sort()));
  };

  async function submit() {
    if (busy) return;
    const found = p.check();
    if (!school.trim()) found.school_name = 'Please type your school.';
    if (!employeeId.trim()) found.employee_id = 'Please type your employee ID.';
    if (mode === 'single' && grades.length !== 1) found.grades_handled = 'Pick the one grade you handle.';
    if (mode === 'multi' && grades.length < 2) found.grades_handled = 'Tick at least two grades, or choose One grade.';
    if (Object.keys(found).length) {
      p.setErrors(found);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      onRegistered(
        await api.registerTeacher({
          ...p.form,
          email: p.form.email.trim().toLowerCase(),
          school_name: school.trim(),
          employee_id: employeeId.trim(),
          grades_mode: mode,
          grades_handled: grades,
        }),
      );
    } catch (e) {
      const fields = api.fieldErrors(e);
      p.setErrors(fields);
      if (!Object.keys(fields).length) setError(messageOf(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <FormScreen title="Teacher sign up" onBack={onBack} footer={<Btn label="Create my account" onPress={submit} loading={busy} />}>
      <PersonFields p={p} />
      <Field label="School" value={school} onChangeText={setSchool} error={p.errors.school_name} />
      <Field label="Employee ID" value={employeeId} onChangeText={setEmployeeId} error={p.errors.employee_id} autoCapitalize="characters" />
      <PasswordFields p={p} />
      <View style={{ alignSelf: 'stretch', gap: space[6] }}>
        <Text maxFontSizeMultiplier={1.25} style={st.groupLabel}>
          Grades you handle
        </Text>
        <Segmented
          options={[
            { key: 'single', label: 'One grade' },
            { key: 'multi', label: 'More than one' },
          ]}
          value={mode}
          onChange={chooseMode}
        />
        <Chips label="Grades you handle" options={GRADES} picked={grades} onToggle={toggle} />
        {p.errors.grades_handled ? <Small style={{ color: colors.bad }}>{p.errors.grades_handled}</Small> : null}
        <Small style={{ textAlign: 'center' }}>Every class holds one grade only. With more than one grade you make a separate class for each.</Small>
      </View>
      {error ? <Notice text={error} /> : null}
      {busy ? <Busy text={slow ? WAKING : 'Creating your account'} /> : null}
    </FormScreen>
  );
}

// ------------------------------------------------------------------ check your email

function CheckEmail({ sent, onLogin }: { sent: Sent; onLogin: () => void }) {
  const [state, setState] = useState<{ busy: boolean; message: string | null; wait: boolean }>({ busy: false, message: null, wait: false });

  async function again() {
    if (state.busy || state.wait) return;
    setState({ busy: true, message: null, wait: false });
    try {
      const r = await api.resendVerification(sent.email);
      setState({ busy: false, message: r.message, wait: true });
    } catch (e) {
      setState({ busy: false, message: messageOf(e), wait: false });
    }
    // The server sends one link a minute for each account, so the button rests for a minute.
    setTimeout(() => setState((v) => ({ ...v, wait: false })), 60_000);
  }

  return (
    <Screen
      anchor="content"
      footer={
        <View style={st.footerStack}>
          <Btn label="I opened the link, log in" onPress={onLogin} />
          <Btn label={state.wait ? 'Sent. Check your inbox' : 'Send the link again'} tone="ghost" icon="paper-plane-tilt" onPress={again} loading={state.busy} disabled={state.wait} />
        </View>
      }
    >
      <View style={st.body}>
        <View style={st.bigIcon}>
          <Icon name="envelope-simple" size={s(48)} color={colors.blueDark} />
        </View>
        <H1 style={{ textAlign: 'center' }}>Almost there!</H1>
        <P style={{ textAlign: 'center' }}>We sent a link to</P>
        <Text maxFontSizeMultiplier={1.25} style={st.sentTo} accessibilityLabel={`We sent a link to ${sent.masked}`}>
          {sent.masked}
        </Text>
        <Small style={{ textAlign: 'center' }}>Open it, then come back here to log in. Look in spam too.</Small>
        {state.message ? <Notice tone="info" text={state.message} /> : null}
      </View>
    </Screen>
  );
}

// ------------------------------------------------------------------ forgot password

function Forgot({ email, onEmail, onBack }: { email: string; onEmail: (value: string) => void; onBack: () => void }) {
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const slow = useSlow(busy);

  async function send() {
    if (busy || !email.trim()) return;
    setBusy(true);
    setError(null);
    try {
      setDone((await api.forgotPassword(email)).message);
    } catch (e) {
      setError(messageOf(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <FormScreen
      title="Forgot password?"
      sub="Type your email and we will send a link to choose a new one."
      onBack={onBack}
      footer={
        done ? (
          <Btn label="Back to log in" onPress={onBack} />
        ) : (
          <View style={st.footerStack}>
            <Btn label="Send the link" icon="paper-plane-tilt" onPress={send} disabled={!email.trim()} loading={busy} />
            <Small style={{ textAlign: 'center' }}>The answer is the same whether or not the address has an account.</Small>
          </View>
        )
      }
    >
      <Field label="Email" value={email} onChangeText={onEmail} placeholder="you@email.com" keyboardType="email-address" autoCapitalize="none" autoComplete="email" textContentType="emailAddress" returnKeyType="send" onSubmitEditing={send} />
      {done ? <Notice tone="info" text={done} /> : null}
      {error ? <Notice text={error} /> : null}
      {busy ? <Busy text={slow ? WAKING : 'Sending'} /> : null}
    </FormScreen>
  );
}

const st = StyleSheet.create({
  body: { alignItems: 'stretch', gap: space[10], paddingVertical: space[8] },
  footerStack: { alignSelf: 'stretch', gap: space[4] },
  pair: { flexDirection: 'row', alignSelf: 'stretch', gap: space[10] },
  groupLabel: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
  role: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space[12],
    minHeight: s(72),
    borderRadius: s(18),
    borderWidth: 2,
    borderColor: colors.line,
    borderBottomWidth: s(5),
    backgroundColor: colors.white,
    padding: space[12],
  },
  roleIcon: { width: s(52), height: s(52), borderRadius: s(26), backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center' },
  bigIcon: { alignSelf: 'center', width: s(88), height: s(88), borderRadius: s(44), backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center' },
  sentTo: { fontFamily: fonts.uiBold, fontSize: s(20), color: colors.ink, textAlign: 'center' },
});
