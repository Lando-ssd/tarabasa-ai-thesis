import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Chips, Field } from '../fields';
import { Btn, Busy, Icon, IconBtn, Notice, P, Screen, Small } from '../ui';
import { Banner, Loading, TonePill, TopBar, useLoad, type Tone } from './kit';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

type ListProps = {
  /** The school year being shown (null is this year). Kept by the app, so coming back from a class keeps the same year. */
  year: string | null;
  onYear: (year: string | null) => void;
  /** Only an Active teacher may make a class or add learners. */
  active: boolean;
  onOpen: (id: number) => void;
  onNew: () => void;
  /** Bumped by the parent after a class is made or changed, so the list fetches again when it comes back into view. */
  refreshKey: number;
  /** Tells the parent what the server said about grades and the current school year, for the New class form. */
  onLoaded?: (list: api.ClassList) => void;
};

/** "2026-2027" as "2026 to 2027", and "(this year)" on the one in progress. */
const yearLabel = (year: string, current: string) => `${year.replace('-', ' to ')}${year === current ? ' (now)' : ''}`;

/** A section is typed by the teacher: "A" or "Section A". Say "Section A" either way, never "Section Section A". */
export const sectionText = (section: string) => (/^section\b/i.test(section.trim()) ? section.trim() : `Section ${section.trim()}`);

const GROUP_TONE: Record<string, Tone> = { support: 'red', instructional: 'blue', independent: 'green' };
const GROUP_SHORT: Record<string, string> = { support: 'Support', instructional: 'Instructional', independent: 'Independent' };

/** Classes: one card for each class of this school year. A class holds one grade, as on the website. */
export function Classes({ year, onYear, active, onOpen, onNew, refreshKey, onLoaded }: ListProps) {
  const { data, error, reload } = useLoad(() => api.teacherClasses(year ?? undefined), [year, refreshKey]);
  useEffect(() => {
    if (data) onLoaded?.(data);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  return (
    <Screen withTabBar>
      <TopBar title="Classes" sub={data ? `School year ${data.schoolYear.replace('-', ' to ')}` : undefined} />

      <View style={{ gap: space[12] }}>
        {!data ? (
          <Loading error={error} onRetry={reload} text="Getting your classes" />
        ) : (
          <>
            {(data.years ?? []).length > 1 ? (
              <Chips
                label="School year"
                options={(data.years ?? []).map((y) => yearLabel(y, data.currentSchoolYear))}
                picked={[yearLabel(data.schoolYear, data.currentSchoolYear)]}
                onToggle={(label) => {
                  const picked = (data.years ?? []).find((y) => yearLabel(y, data.currentSchoolYear) === label) ?? null;
                  onYear(picked === data.currentSchoolYear ? null : picked);
                }}
              />
            ) : null}
            {data.readOnly ? <Notice tone="info" text="This is a past school year, so it can only be read." /> : null}

            {data.classes.map((c) => (
              <ClassCardView key={c.id} card={c} onPress={() => onOpen(c.id)} />
            ))}

            {data.classes.length === 0 ? (
              <View style={{ gap: space[8], paddingVertical: space[16] }}>
                <P style={{ textAlign: 'center' }}>You have no class in this school year yet.</P>
              </View>
            ) : null}

            {data.readOnly ? null : active ? (
              <Btn label="New class" tone="ghost" icon="plus" onPress={onNew} />
            ) : (
              <Banner tone="amber" icon="info" title="New classes open once you are approved" text="Your school details are being checked." />
            )}
          </>
        )}
      </View>
    </Screen>
  );
}

function ClassCardView({ card, onPress }: { card: api.ClassCard; onPress: () => void }) {
  const groups = card.groups.filter((g) => g.count > 0);
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${card.name}, ${card.grade}, ${card.learnerCount} learners`}
      onPress={onPress}
      style={({ pressed }) => [st.card, pressed && { opacity: 0.75 }]}
    >
      <View style={st.cardHead}>
        <View style={{ flex: 1 }}>
          <Text maxFontSizeMultiplier={1.25} style={st.cardName}>
            {card.name}
          </Text>
          <Small>
            {card.grade} · {sectionText(card.section)}
          </Small>
        </View>
        <TonePill text={`${card.learnerCount} ${card.learnerCount === 1 ? 'learner' : 'learners'}`} tone="blue" />
      </View>
      <View style={st.meta}>
        <Icon name="note-pencil" size={s(18)} color={colors.slate} />
        <Small>
          {card.activityCount === 0 ? 'No activities assigned yet' : `${card.activityCount} ${card.activityCount === 1 ? 'activity' : 'activities'} assigned`}
        </Small>
        {card.groupTag ? <TonePill text={card.groupTag} tone="grey" /> : null}
      </View>
      {groups.length > 0 ? (
        <View style={st.groups}>
          {groups.map((g) => (
            <TonePill key={g.key} text={`${GROUP_SHORT[g.key]} ${g.count}`} tone={GROUP_TONE[g.key]} />
          ))}
        </View>
      ) : null}
      {card.readOnly ? <TonePill text="Read only" tone="amber" /> : null}
    </Pressable>
  );
}

// ------------------------------------------------------------------ new class and edit class

type FormProps = {
  mode: 'new' | 'edit';
  /** The class being changed (edit), or null (new). */
  card: api.ClassCard | null;
  /** The grades this teacher handles. */
  grades: string[];
  schoolYear: string;
  onBack: () => void;
  onSaved: (card: api.ClassCard) => void;
};

/**
 * Make a class or change one. A class holds one grade only, so the grade is one of the grades the teacher handles, and it
 * cannot be changed under learners it would no longer fit (the server says who is in the way). The school year is not
 * editable: a class is never edited into next year's class.
 */
export function ClassForm({ mode, card, grades, schoolYear, onBack, onSaved }: FormProps) {
  const [name, setName] = useState(card?.name ?? '');
  const [section, setSection] = useState(card?.section ?? '');
  const [grade, setGrade] = useState<string>(card?.grade ?? (grades.length === 1 ? grades[0] : ''));
  const [tag, setTag] = useState(card?.groupTag ?? '');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const clear = (key: string) => setErrors((e) => ({ ...e, [key]: '' }));

  async function save() {
    if (busy) return;
    const found: Record<string, string> = {};
    if (!name.trim()) found.name = 'Please type a name for the class.';
    if (!section.trim()) found.section = 'Please type the section.';
    if (!grade) found.grade_level = 'Pick the grade of this class.';
    if (Object.keys(found).length) {
      setErrors(found);
      return;
    }
    setBusy(true);
    setError(null);
    const body: api.ClassBody = { name: name.trim(), section: section.trim(), grade_level: grade, group_tag: tag.trim() };
    try {
      const saved = mode === 'new' ? await api.createClass({ ...body, school_year: schoolYear }) : await api.updateClass(card!.id, body);
      onSaved(saved.class);
    } catch (e) {
      const fields = api.fieldErrors(e);
      setErrors(fields);
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
        footer={<Btn label={mode === 'new' ? 'Make the class' : 'Save changes'} onPress={save} loading={busy} />}
      >
        <View style={{ gap: space[10], paddingVertical: space[8] }}>
          <TopBar title={mode === 'new' ? 'New class' : 'Edit class'} sub={`School year ${schoolYear.replace('-', ' to ')}`} />
          <Field label="Class name" value={name} onChangeText={(t) => (clear('name'), setName(t))} error={errors.name} placeholder="e.g. Sampaguita" />
          <Field label="Section" value={section} onChangeText={(t) => (clear('section'), setSection(t))} error={errors.section} placeholder="e.g. A" />
          <View style={{ gap: space[6] }}>
            <Text maxFontSizeMultiplier={1.25} style={st.label}>
              Grade
            </Text>
            <Chips label="Grade" options={grades} picked={grade ? [grade] : []} onToggle={(g) => (clear('grade_level'), setGrade(g))} />
            {errors.grade_level ? <Small style={{ color: colors.bad }}>{errors.grade_level}</Small> : <Small>One class, one grade. For another grade, make another class.</Small>}
          </View>
          <Field
            label="Focus group (optional)"
            value={tag}
            onChangeText={setTag}
            placeholder="e.g. Basic Phonics"
            hint="A label for what this class is working on. Give two classes the same focus to assign one activity to both at once. It does not sort learners."
          />
          {error ? <Notice text={error} /> : null}
          {busy ? <Busy text="Saving" /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

const st = StyleSheet.create({
  card: {
    backgroundColor: colors.white,
    borderRadius: s(18),
    borderWidth: 2,
    borderColor: colors.line,
    borderBottomWidth: s(5),
    padding: space[14],
    gap: space[8],
  },
  cardHead: { flexDirection: 'row', alignItems: 'center', gap: space[10] },
  cardName: { fontFamily: fonts.display, fontSize: s(22), lineHeight: s(28), color: colors.ink },
  meta: { flexDirection: 'row', alignItems: 'center', gap: space[6], flexWrap: 'wrap' },
  groups: { flexDirection: 'row', gap: space[6], flexWrap: 'wrap' },
  label: { fontFamily: fonts.uiBold, fontSize: s(13), letterSpacing: s(0.8), textTransform: 'uppercase', color: colors.slate },
});
