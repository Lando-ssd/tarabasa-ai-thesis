import { useState, type ReactNode } from 'react';
import { KeyboardAvoidingView, Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Chips, Field, Segmented } from '../fields';
import { Btn, IconBtn, Notice, P, Screen, Small } from '../ui';
import { Loading, TopBar, useLoad } from '../teacher/kit';
import { Meter } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

// Five steps, the same questions as the website's wizard, one idea on each screen: who the child is, a character, how they read now,
// three quick questions for their grade, and a 4 digit PIN. The server works out the starting level; the phone only asks.
const STEPS = ['About your child', 'Choose a character', 'How they read now', 'A few quick questions', 'Set a PIN'];

/** Letters (any language), spaces, hyphens and apostrophes only: the same rule the server enforces, for quick feedback. */
const NAME = /^[\p{L}\s'-]+$/u;

/** Which step a field's message belongs to, so a refusal from the server brings the parent to the right screen. */
const STEP_OF: Record<string, number> = {
  first_name: 0, middle_name: 0, last_name: 0, grade_level: 0, home_language: 0,
  avatar_id: 1,
  reading_stage: 2, supports: 2, interests: 2,
  q1: 3, q2: 3, q3: 3,
  pin: 4, pin_confirmation: 4,
};

const ANSWERS: { key: string; label: string }[] = [
  { key: 'yes', label: 'Yes' },
  { key: 'no', label: 'Not yet' },
  { key: 'unsure', label: 'Not sure' },
];

type Props = { onBack: () => void; onDone: () => void; onCard: (child: api.ParentChild) => void };

/** Add a child. When it is saved, the child's Learner Code is shown at once, with the QR card a teacher can scan. */
export function AddChild({ onBack, onDone, onCard }: Props) {
  const { data: o, error, reload } = useLoad(() => api.parentChildOptions());
  const [step, setStep] = useState(0);
  const [first, setFirst] = useState('');
  const [mi, setMi] = useState('');
  const [last, setLast] = useState('');
  const [grade, setGrade] = useState<string | null>(null);
  const [language, setLanguage] = useState<string | null>(null);
  const [avatar, setAvatar] = useState<string | null>(null);
  const [stage, setStage] = useState<string | null>(null);
  const [supports, setSupports] = useState<string[]>([]);
  const [interests, setInterests] = useState<string[]>([]);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [pin, setPin] = useState('');
  const [again, setAgain] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [made, setMade] = useState<api.ParentChild | null>(null);

  const clear = (key: string) => setErrors((e) => ({ ...e, [key]: '' }));
  const toggle = (list: string[], set: (v: string[]) => void, key: string) => set(list.includes(key) ? list.filter((x) => x !== key) : [...list, key]);

  if (made) {
    return (
      <Screen
        withTabBar
        anchor="content"
        footer={
          <View style={{ gap: space[8] }}>
            <Btn label="Show the card" icon="qr-code" onPress={() => onCard(made)} />
            <Btn label="Done" tone="ghost" onPress={onDone} />
          </View>
        }
      >
        <View style={st.done}>
          <Text maxFontSizeMultiplier={1.2} style={st.doneTitle}>
            {made.firstName} is ready!
          </Text>
          <P style={{ textAlign: 'center' }}>Their Learner Code is below. {made.firstName} signs in with this code and the PIN you just set, or with the QR card.</P>
          <Text selectable style={st.code}>
            {made.code}
          </Text>
          <Small style={{ textAlign: 'center' }}>Keep the PIN private. A teacher only needs the code, never the PIN.</Small>
        </View>
      </Screen>
    );
  }

  if (!o) {
    return (
      <Screen withTabBar header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />}>
        <Loading error={error} onRetry={reload} text="Getting ready" />
      </Screen>
    );
  }

  const questions = grade ? (o.placementQuestions[grade] ?? []) : [];

  /** The checks the website makes on each step, so the parent hears about a mistake on the step it is on. */
  function check(at: number): Record<string, string> {
    const found: Record<string, string> = {};
    if (at === 0) {
      if (!first.trim()) found.first_name = 'Please type their first name.';
      else if (!NAME.test(first)) found.first_name = 'A name has letters only, no numbers.';
      if (!last.trim()) found.last_name = 'Please type their last name.';
      else if (!NAME.test(last)) found.last_name = 'A name has letters only, no numbers.';
      if (!grade) found.grade_level = 'Pick their grade.';
    }
    if (at === 1 && !avatar) found.avatar_id = 'Choose a character.';
    if (at === 2 && !stage) found.reading_stage = 'Pick the one that is closest. A guess is fine.';
    if (at === 3 && ['q1', 'q2', 'q3'].some((k) => !answers[k])) found.q1 = 'Please answer all three. "Not sure" is fine.';
    if (at === 4) {
      if (!/^\d{4}$/.test(pin)) found.pin = 'The PIN is 4 digits.';
      else if (pin !== again) found.pin_confirmation = 'The two PINs are not the same.';
    }
    return found;
  }

  async function next() {
    if (busy) return;
    const found = check(step);
    setErrors(found);
    if (Object.keys(found).length) return;
    if (step < STEPS.length - 1) {
      setStep(step + 1);
      return;
    }

    setBusy(true);
    setProblem(null);
    try {
      const done = await api.addChild({
        first_name: first.trim(),
        ...(mi.trim() ? { middle_name: mi.trim() } : {}),
        last_name: last.trim(),
        grade_level: grade!,
        avatar_id: avatar!,
        reading_stage: stage!,
        ...(language ? { home_language: language } : {}),
        supports,
        interests,
        q1: answers.q1,
        q2: answers.q2,
        q3: answers.q3,
        pin,
        pin_confirmation: again,
      });
      setMade(done.child);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      const where = Object.keys(fields).map((k) => STEP_OF[k] ?? 0);
      if (where.length) setStep(Math.min(...where));
      else setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  const last_ = step === STEPS.length - 1;

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen
        withTabBar
        header={<IconBtn icon="caret-left" label={step === 0 ? 'Back' : 'Previous step'} onPress={() => (step === 0 ? onBack() : setStep(step - 1))} />}
        footer={<Btn label={last_ ? 'Add my child' : 'Next'} onPress={next} loading={busy} />}
      >
        <View style={{ gap: space[12], paddingTop: space[8] }}>
          <View style={{ gap: space[6] }}>
            <Small>
              Step {step + 1} of {STEPS.length}
            </Small>
            <Meter value={step + 1} max={STEPS.length} />
            <TopBar title={STEPS[step]} />
          </View>

          {step === 0 ? (
            <>
              <Field label="First name" value={first} onChangeText={(t) => (clear('first_name'), setFirst(t))} error={errors.first_name} autoComplete="off" />
              <Field label="Middle name (optional)" value={mi} onChangeText={setMi} error={errors.middle_name} />
              <Field label="Last name" value={last} onChangeText={(t) => (clear('last_name'), setLast(t))} error={errors.last_name} />
              <Block title="Grade" error={errors.grade_level}>
                <Chips label="Grade" options={o.grades} picked={grade ? [grade] : []} onToggle={(g) => (clear('grade_level'), setGrade(g), setAnswers({}))} />
              </Block>
              <Block title="Language spoken at home (optional)" hint="Helps us understand how much English your child hears at home. It does not change the activities.">
                <Chips label="Language at home" options={o.homeLanguages} picked={language ? [language] : []} onToggle={(l) => setLanguage(language === l ? null : l)} />
              </Block>
            </>
          ) : null}

          {step === 1 ? (
            <>
              <P>Pick a character for {first.trim() || 'your child'}.</P>
              <View style={st.grid} accessibilityRole="radiogroup">
                {o.avatars.map((a) => {
                  const on = a === avatar;
                  return (
                    <Pressable key={a} accessibilityRole="radio" accessibilityState={{ selected: on }} accessibilityLabel={`Character ${a}`} onPress={() => (clear('avatar_id'), setAvatar(a))} style={[st.avatar, on && st.avatarOn]}>
                      <Text style={st.avatarText}>{a}</Text>
                    </Pressable>
                  );
                })}
              </View>
              {errors.avatar_id ? <Small style={{ color: colors.bad }}>{errors.avatar_id}</Small> : null}
            </>
          ) : null}

          {step === 2 ? (
            <>
              <P>Your best guess is fine. A quick reading check finds the real starting point. This only sets a start.</P>
              <View style={{ gap: space[8] }} accessibilityRole="radiogroup">
                {o.readingStages.map((r) => {
                  const on = r.key === stage;
                  return (
                    <Pressable key={r.key} accessibilityRole="radio" accessibilityState={{ selected: on }} onPress={() => (clear('reading_stage'), setStage(r.key))} style={[st.option, on && st.optionOn]}>
                      <Text maxFontSizeMultiplier={1.25} style={st.optionText}>
                        {r.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
              {errors.reading_stage ? <Small style={{ color: colors.bad }}>{errors.reading_stage}</Small> : null}
              <Block title="What helps most (choose any)" hint="These switch on supports such as voice help. They do not decide the activities.">
                <Chips label="What helps most" options={o.supports.map((x) => x.label)} picked={o.supports.filter((x) => supports.includes(x.key)).map((x) => x.label)} onToggle={(label) => toggle(supports, setSupports, o.supports.find((x) => x.label === label)!.key)} />
              </Block>
              <Block title="Topics they like (choose any)">
                <Chips label="Topics they like" options={o.interests.map((x) => x.label)} picked={o.interests.filter((x) => interests.includes(x.key)).map((x) => x.label)} onToggle={(label) => toggle(interests, setInterests, o.interests.find((x) => x.label === label)!.key)} />
              </Block>
            </>
          ) : null}

          {step === 3 ? (
            <>
              <P>Three questions for {grade}. A guess is fine. {first.trim() || 'Your child'}'s first reading check finds the real starting point.</P>
              {questions.map((q, i) => (
                <View key={q} style={{ gap: space[6] }}>
                  <Text maxFontSizeMultiplier={1.25} style={st.question}>
                    {i + 1}. {q}
                  </Text>
                  <Segmented options={ANSWERS} value={(answers[`q${i + 1}`] ?? '') as string} onChange={(v) => (clear('q1'), setAnswers((a) => ({ ...a, [`q${i + 1}`]: v })))} />
                </View>
              ))}
              {errors.q1 || errors.q2 || errors.q3 ? <Small style={{ color: colors.bad }}>{errors.q1 || errors.q2 || errors.q3}</Small> : null}
            </>
          ) : null}

          {step === 4 ? (
            <>
              <P>{first.trim() || 'Your child'} uses this PIN, with their Learner Code or card, to open their own app. Choose 4 digits and keep them private.</P>
              <Field label="PIN" value={pin} onChangeText={(t) => (clear('pin'), setPin(t.replace(/\D/g, '').slice(0, 4)))} keyboardType="number-pad" maxLength={4} password error={errors.pin} />
              <Field label="PIN again" value={again} onChangeText={(t) => (clear('pin_confirmation'), setAgain(t.replace(/\D/g, '').slice(0, 4)))} keyboardType="number-pad" maxLength={4} password error={errors.pin_confirmation} />
            </>
          ) : null}

          {problem ? <Notice text={problem} /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

function Block({ title, hint, error, children }: { title: string; hint?: string; error?: string; children: ReactNode }) {
  return (
    <View style={{ gap: space[6] }}>
      <Text maxFontSizeMultiplier={1.25} style={st.label}>
        {title}
      </Text>
      {children}
      {error ? <Small style={{ color: colors.bad }}>{error}</Small> : hint ? <Small>{hint}</Small> : null}
    </View>
  );
}

const st = StyleSheet.create({
  label: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: space[10], justifyContent: 'center' },
  avatar: { width: s(72), height: s(72), borderRadius: s(36), backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center', borderWidth: 3, borderColor: 'transparent' },
  avatarOn: { borderColor: colors.orange, backgroundColor: colors.orangeSoft },
  avatarText: { fontSize: s(38), lineHeight: s(48) },
  option: { minHeight: size.button, justifyContent: 'center', backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, paddingHorizontal: space[14], paddingVertical: space[10] },
  optionOn: { borderColor: colors.blue, backgroundColor: colors.sky },
  optionText: { fontFamily: fonts.uiBold, fontSize: s(17), lineHeight: s(22), color: colors.ink },
  question: { fontFamily: fonts.uiBold, fontSize: s(17), lineHeight: s(23), color: colors.ink },
  done: { alignItems: 'center', gap: space[12], paddingVertical: space[20] },
  doneTitle: { fontFamily: fonts.display, fontSize: s(30), lineHeight: s(38), color: colors.orange, textAlign: 'center' },
  code: { fontFamily: fonts.display, fontSize: s(36), lineHeight: s(44), color: colors.ink, letterSpacing: s(1) },
});
