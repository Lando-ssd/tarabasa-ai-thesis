import { useEffect, useState, type ReactNode } from 'react';
import { KeyboardAvoidingView, Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Chips, Field } from '../fields';
import { Btn, Icon, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { Banner, Loading, useLoad } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

const LEVELS: api.Level[] = ['Easy', 'Medium', 'Hard'];

type Props = {
  onBack: () => void;
  /** The request was saved. The Activities list follows it from there. */
  onStarted: () => void;
};

/**
 * Write new activities. Pick the grade, the skill and the kind of reading, then how many of each level you want. A request is
 * written in the background (about two minutes) and one credit is used only when the activities are saved, so a failure costs
 * nothing. "For a class" tells the app to write for what that class's readings show, and says what it found before you press Write.
 */
export function Generate({ onBack, onStarted }: Props) {
  const { data: o, error, reload } = useLoad(() => api.generateOptions());

  // The activity writer sleeps when it has not been used; opening this screen wakes it while you choose.
  useEffect(() => {
    api.warmGenerator();
  }, []);

  const [grade, setGrade] = useState<string | null>(null);
  const [competency, setCompetency] = useState<string | null>(null);
  const [type, setType] = useState<string | null>(null);
  const [counts, setCounts] = useState<Record<api.Level, number>>({ Easy: 0, Medium: 1, Hard: 0 });
  const [classId, setClassId] = useState<number | null>(null);
  const [topic, setTopic] = useState('');
  const [notes, setNotes] = useState('');
  const [basis, setBasis] = useState<{ enough: boolean; lines: string[] } | null>(null);
  const [basisError, setBasisError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const g = grade ?? o?.grades[0] ?? null;
  const comp = o?.competencies.find((c) => c.key === (competency ?? o.competencies[0]?.key)) ?? null;
  const kind = type && comp?.types.includes(type) ? type : (comp?.types[0] ?? null);
  const gradeNumber = g ? g.replace('Grade ', '') : '1';
  const classesForGrade = o?.classes.filter((c) => c.grade === g) ?? [];

  // What the class's own readings say, asked as soon as a class is picked, so it can be read before anything is written.
  useEffect(() => {
    setBasis(null);
    setBasisError(null);
    if (classId === null || !g || !kind) return;
    let alive = true;
    api
      .generateBasis(classId, g, kind)
      .then((b) => alive && setBasis({ enough: b.enough, lines: b.lines }))
      .catch((e) => alive && setBasisError(e instanceof Error ? e.message : 'Could not read the class just now.'));
    return () => {
      alive = false;
    };
  }, [classId, g, kind]);

  if (!o) {
    return (
      <Screen withTabBar header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />}>
        <Loading error={error} onRetry={reload} text="Getting ready" />
      </Screen>
    );
  }

  const total = counts.Easy + counts.Medium + counts.Hard;
  const most = Math.max(counts.Easy, counts.Medium, counts.Hard);
  const seconds = o.baseSeconds + o.extraSeconds * Math.max(0, most - 1);
  const noCredits = o.credits <= 0;
  const running = !!o.generation?.active;

  async function write() {
    if (busy || !g || !comp || !kind) return;
    const found: Record<string, string> = {};
    if (total === 0) found.levels = 'Choose at least one level to write.';
    setErrors(found);
    if (Object.keys(found).length) return;

    setBusy(true);
    setProblem(null);
    try {
      await api.generateActivities({
        grade_level: g,
        competency: comp.key,
        activity_type: kind,
        levels: counts,
        ...(topic.trim() ? { topic: topic.trim() } : {}),
        ...(notes.trim() ? { teacher_notes: notes.trim() } : {}),
        ...(classId !== null ? { class_id: classId } : {}),
      });
      onStarted();
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
      const first = Object.values(fields)[0];
      setProblem(first ?? (e instanceof Error ? e.message : 'Something went wrong. Please try again.'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen
        withTabBar
        header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />}
        footer={<Btn label={total === 0 ? 'Choose how many' : `Write ${total} ${total === 1 ? 'activity' : 'activities'}`} icon="sparkle" onPress={write} disabled={noCredits || running || total === 0} loading={busy} />}
      >
        <View style={{ gap: space[14], paddingTop: space[8] }}>
          <View>
            <Text maxFontSizeMultiplier={1.2} style={st.title}>
              Write new activities
            </Text>
            <Small>
              {o.credits} free {o.credits === 1 ? 'credit' : 'credits'} left. One credit for each request, used only when the activities are saved.
            </Small>
          </View>

          {noCredits ? (
            <Banner tone="amber" icon="info" title="You are out of free credits" text={o.locked ? 'More free credits open once your school is approved.' : 'Share an approved activity to the Repository to earn 2 more.'} />
          ) : null}
          {running ? <Banner tone="blue" icon="sparkle" title="Your last request is still being written" text="Wait for it to finish, then ask for more." /> : null}

          <Section title="Grade">
            <Chips label="Grade" options={o.grades} picked={g ? [g] : []} onToggle={(x) => (setGrade(x), setClassId(null))} />
          </Section>

          <Section title="Skill">
            <Chips label="Skill" options={o.competencies.map((c) => c.label)} picked={comp ? [comp.label] : []} onToggle={(label) => (setCompetency(o.competencies.find((c) => c.label === label)?.key ?? null), setType(null))} />
            {comp ? <Small>{comp.description}</Small> : null}
          </Section>

          <Section title="Kind of reading">
            <Chips label="Kind of reading" options={(comp?.types ?? []).map((t) => o.typeLabels[t] ?? t)} picked={kind ? [o.typeLabels[kind] ?? kind] : []} onToggle={(label) => setType((comp?.types ?? []).find((t) => (o.typeLabels[t] ?? t) === label) ?? null)} />
          </Section>

          <Section title="How many of each level">
            {LEVELS.map((level) => {
              const band = kind ? o.bands[kind]?.[gradeNumber]?.[level.toLowerCase()] : undefined;
              const have = kind ? o.have[gradeNumber]?.[kind]?.[level] : undefined;
              return (
                <Stepper
                  key={level}
                  label={level}
                  value={counts[level]}
                  max={o.maxPerLevel}
                  sub={[band ? `${band[0]} to ${band[1]} words` : null, have ? `you have ${have[0]} approved, ${have[1]} waiting` : null].filter(Boolean).join(' · ')}
                  onChange={(n) => (setErrors((e) => ({ ...e, levels: '' })), setCounts((c) => ({ ...c, [level]: n })))}
                />
              );
            })}
            {errors.levels ? <Small style={{ color: colors.bad }}>{errors.levels}</Small> : <Small>About {Math.max(1, Math.round(seconds / 60))} {Math.round(seconds / 60) <= 1 ? 'minute' : 'minutes'} to write. You can leave the screen while it works.</Small>}
          </Section>

          <Section title="For a class (optional)">
            {classesForGrade.length === 0 ? (
              <Small>You have no {g} class this school year, so the activities are written for the grade in general.</Small>
            ) : (
              <>
                <Chips label="For a class" options={classesForGrade.map((c) => c.name)} picked={classId !== null ? [classesForGrade.find((c) => c.id === classId)?.name ?? ''] : []} onToggle={(name) => setClassId((cur) => { const id = classesForGrade.find((c) => c.name === name)?.id ?? null; return id === cur ? null : id; })} />
                {classId !== null ? <Basis basis={basis} error={basisError} /> : <Small>Pick a class and the activities are written for what its readings show, such as the step most children are on and the sounds they find hard.</Small>}
              </>
            )}
          </Section>

          <Field label="Topic (optional)" value={topic} onChangeText={setTopic} placeholder="e.g. Animals on the farm" maxLength={200} error={errors.topic} />
          <Field label="Anything else for the writer (optional)" value={notes} onChangeText={setNotes} lines={2} maxLength={1000} error={errors.teacher_notes} />
          <Small>Please do not type a child's name or other personal details here or in Topic. This text is sent to an AI service.</Small>

          {problem ? <Notice text={problem} /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={{ gap: space[8] }}>
      <Text maxFontSizeMultiplier={1.25} style={st.label}>
        {title}
      </Text>
      {children}
    </View>
  );
}

/** What the class's readings show, in the same plain lines the website shows. Nothing is hidden: this is exactly what shapes the writing. */
function Basis({ basis, error }: { basis: { enough: boolean; lines: string[] } | null; error: string | null }) {
  if (error) return <Notice text={error} />;
  if (!basis) return <Small>Reading the class's readings</Small>;
  return (
    <Panel style={{ gap: space[6] }}>
      <Text maxFontSizeMultiplier={1.25} style={st.panelTitle}>
        {basis.enough ? "Based on this class's readings" : 'Not enough readings yet'}
      </Text>
      {basis.lines.map((l, i) => (
        <P key={i}>{l}</P>
      ))}
    </Panel>
  );
}

/** A number with a minus and a plus, both easy to tap. */
function Stepper({ label, value, max, sub, onChange }: { label: string; value: number; max: number; sub: string; onChange: (n: number) => void }) {
  return (
    <View style={st.stepper}>
      <View style={{ flex: 1, gap: space[2] }}>
        <Text maxFontSizeMultiplier={1.25} style={st.stepLabel}>
          {label}
        </Text>
        {sub ? <Small>{sub}</Small> : null}
      </View>
      <Round icon="minus" label={`Fewer ${label}`} disabled={value <= 0} onPress={() => onChange(value - 1)} />
      <Text maxFontSizeMultiplier={1.2} style={st.stepValue} accessibilityLabel={`${value} ${label}`}>
        {value}
      </Text>
      <Round icon="plus" label={`More ${label}`} disabled={value >= max} onPress={() => onChange(value + 1)} />
    </View>
  );
}

function Round({ icon, label, disabled, onPress }: { icon: string; label: string; disabled: boolean; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled }} disabled={disabled} onPress={onPress} hitSlop={4} style={({ pressed }) => [st.round, pressed && { opacity: 0.7 }, disabled && { opacity: 0.35 }]}>
      <Icon name={icon} size={s(24)} color={colors.blueDark} />
    </Pressable>
  );
}

const st = StyleSheet.create({
  title: { fontFamily: fonts.display, fontSize: s(26), lineHeight: s(31), color: colors.orange },
  label: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
  panelTitle: { fontFamily: fonts.displayMedium, fontSize: s(18), lineHeight: s(23), color: colors.ink },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: space[8], minHeight: s(64), backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, paddingHorizontal: space[12], paddingVertical: space[8] },
  stepLabel: { fontFamily: fonts.uiBold, fontSize: s(18), color: colors.ink },
  stepValue: { fontFamily: fonts.display, fontSize: s(26), minWidth: s(30), textAlign: 'center', color: colors.ink },
  round: { width: size.touch, height: size.touch, borderRadius: size.touch / 2, backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center' },
});
