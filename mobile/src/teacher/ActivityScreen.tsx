import { useState } from 'react';
import { KeyboardAvoidingView, Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Chips, Field, Segmented } from '../fields';
import { Btn, H2, IconBtn, Notice, P, Panel, Screen, Small } from '../ui';
import { LEVEL_TONE } from './Activities';
import { Banner, Loading, TonePill, TopBar, useLoad } from './kit';
import { colors, fonts, size, space } from '../theme';
import { s } from '../scale';

type SubView = 'main' | 'edit' | 'assign' | 'share';

type Props = {
  id: number;
  onBack: () => void;
  /** Called after something changed, so the list behind this screen fetches again. */
  onChanged: () => void;
  locked: boolean;
};

/**
 * One activity: the words a child reads, why it sits in its level, who it has been given to, and (once you are approved) the moves:
 * put it in a level, edit and approve a draft, give it to a class, a group or a child, share it, or take back a rejection.
 * Everything is checked again on the server, so a button here is a convenience and never the only guard.
 */
export function ActivityScreen({ id, onBack, onChanged, locked }: Props) {
  const { data, error, reload } = useLoad(() => api.teacherActivity(id), [id]);
  const [view, setView] = useState<SubView>('main');
  const [message, setMessage] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function run(work: () => Promise<{ message: string }>) {
    if (busy) return;
    setBusy(true);
    setProblem(null);
    try {
      const done = await work();
      setMessage(done.message);
      setView('main');
      reload();
      onChanged();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  if (!data) {
    return (
      <Screen withTabBar>
        <TopBar title="Activity" onBack={onBack} />
        <Loading error={error} onRetry={reload} text="Getting the activity" />
      </Screen>
    );
  }

  if (view === 'edit') return <EditView a={data} busy={busy} problem={problem} onBack={() => setView('main')} onSave={(body) => run(() => api.editActivity(id, body))} />;
  if (view === 'assign') return <AssignView a={data} busy={busy} problem={problem} onBack={() => setView('main')} onGive={(t) => run(() => api.assignActivity(id, t))} />;
  if (view === 'share') return <ShareView a={data} busy={busy} problem={problem} onBack={() => setView('main')} onShare={(b) => run(() => api.shareActivity(id, b))} />;

  const a = data;
  return (
    <Screen withTabBar>
      <TopBar title={a.title} sub={`${a.grade} · ${a.type}`} onBack={onBack} />

      <View style={{ gap: space[12] }}>
        <View style={st.pills}>
          {a.level ? <TonePill text={a.level} tone={LEVEL_TONE[a.level]} /> : null}
          <TonePill text={a.status === 'Draft' ? 'Waiting for review' : a.status} tone={a.status === 'Approved' ? 'green' : a.status === 'Rejected' ? 'grey' : 'amber'} />
          {a.words ? <TonePill text={`${a.words} words`} tone="blue" /> : null}
          {a.shared ? <TonePill text="Shared" tone="blue" /> : null}
        </View>

        {message ? <Notice tone="info" text={message} /> : null}
        {problem ? <Notice text={problem} /> : null}

        <Panel style={{ gap: space[8] }}>
          <Small>What the child reads aloud</Small>
          <Text maxFontSizeMultiplier={1.25} style={st.passage}>
            {a.passage}
          </Text>
          <Small>{a.instructions}</Small>
        </Panel>

        {a.basis ? (
          <Panel style={{ gap: space[6] }}>
            <H2>Written for {a.basis.className}</H2>
            {a.basis.lines.map((l, i) => (
              <P key={i}>{l}</P>
            ))}
          </Panel>
        ) : null}

        <WhyLevel a={a} />

        {a.givenTo.length > 0 ? (
          <View style={{ gap: space[6] }}>
            <H2>Given to</H2>
            {a.givenTo.map((g, i) => (
              <P key={i}>{g}</P>
            ))}
          </View>
        ) : null}

        {a.listing ? (
          <P>
            In the Repository: {a.listing.price}
            {a.listing.stars !== null ? `. Rated ${a.listing.stars} of 5 by ${a.listing.ratings} ${a.listing.ratings === 1 ? 'parent' : 'parents'}` : '. No ratings yet'}.
          </P>
        ) : null}

        {locked ? (
          <Banner tone="amber" icon="info" title="Moving and giving out open once you are approved" text="You can read this activity now." />
        ) : (
          <Actions a={a} busy={busy} onMove={(level) => run(() => api.placeActivity(id, level))} onRestore={() => run(() => api.restoreActivity(id))} onView={setView} />
        )}
      </View>
    </Screen>
  );
}

/** "Why this level": the plain meaning of the level, the usual word range for the grade, and a note when the length does not fit. */
function WhyLevel({ a }: { a: api.ActivityPage }) {
  if (!a.level) return null;
  return (
    <Panel style={{ gap: space[6] }}>
      <H2>Why {a.level}</H2>
      {a.levelMeaning ? <P>{a.levelMeaning}</P> : null}
      {a.band ? (
        <P>
          The usual range for this grade is {a.band.min} to {a.band.max} words. This one has {a.words ?? 'an unknown number of'} words
          {a.lengthFits === false ? ', which is outside that range, so check the level.' : '.'}
        </P>
      ) : null}
      {a.moved && a.aiLevel ? <P>The AI suggested {a.aiLevel}. You put it in {a.level}.</P> : null}
      {a.features.length > 0 ? <Small>What the AI looked at: {a.features.join(', ')}.</Small> : null}
      <Small>Levels are this app's own guide, from how familiar the words are and how long the text is. They are not official DepEd scores, so the decision is yours.</Small>
    </Panel>
  );
}

function Actions({ a, busy, onMove, onRestore, onView }: { a: api.ActivityPage; busy: boolean; onMove: (level: api.Level | 'reject') => void; onRestore: () => void; onView: (v: SubView) => void }) {
  if (a.status === 'Rejected') {
    return <Btn label="Take back the rejection" icon="arrow-counter-clockwise" tone="ghost" loading={busy} onPress={onRestore} />;
  }
  const levels: api.Level[] = ['Easy', 'Medium', 'Hard'];
  return (
    <View style={{ gap: space[10] }}>
      <H2>{a.status === 'Draft' ? 'Put it in a level' : 'Move to another level'}</H2>
      <View style={st.levels}>
        {levels.map((level) => {
          const on = a.status === 'Approved' && a.level === level;
          return (
            <Pressable
              key={level}
              accessibilityRole="button"
              accessibilityLabel={on ? `${level}, where it is now` : `Put in ${level}`}
              accessibilityState={{ selected: on, disabled: busy || on }}
              disabled={busy || on}
              onPress={() => onMove(level)}
              style={({ pressed }) => [st.levelBtn, on && { backgroundColor: colors.ink }, pressed && { opacity: 0.7 }, busy && { opacity: 0.5 }]}
            >
              <Text maxFontSizeMultiplier={1.15} style={[st.levelText, on && { color: colors.white }]}>
                {level}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {a.status === 'Draft' ? (
        <>
          <Btn label="Edit the words and approve" icon="note-pencil" tone="blue" onPress={() => onView('edit')} />
          <Btn label="Reject" tone="ghost" compact loading={busy} onPress={() => onMove('reject')} />
        </>
      ) : (
        <>
          <Btn label="Give to a class or learner" icon="paper-plane-tilt" onPress={() => onView('assign')} />
          {a.shared ? <Small>This activity is already in the Repository.</Small> : <Btn label="Share to the Repository" icon="share-network" tone="ghost" onPress={() => onView('share')} />}
          <Btn label="Reject" tone="ghost" compact loading={busy} onPress={() => onMove('reject')} />
        </>
      )}
    </View>
  );
}

// ------------------------------------------------------------------ edit

function EditView({ a, busy, problem, onBack, onSave }: { a: api.ActivityPage; busy: boolean; problem: string | null; onBack: () => void; onSave: (body: { title: string; instructions: string; passage_text: string; level: api.Level | null }) => void }) {
  const [title, setTitle] = useState(a.title);
  const [instructions, setInstructions] = useState(a.instructions);
  const [passage, setPassage] = useState(a.passage ?? '');
  const [level, setLevel] = useState<api.Level | null>(a.level);
  const [errors, setErrors] = useState<Record<string, string>>({});

  function save() {
    const found: Record<string, string> = {};
    if (!title.trim()) found.title = 'Please type a title.';
    if (!instructions.trim()) found.instructions = 'Please type what the child should do.';
    if (!passage.trim()) found.passage_text = 'Please type the words the child reads.';
    setErrors(found);
    if (Object.keys(found).length === 0) onSave({ title: title.trim(), instructions: instructions.trim(), passage_text: passage.trim(), level });
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen withTabBar anchor="content" header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />} footer={<Btn label="Save and approve" onPress={save} loading={busy} />}>
        <View style={{ gap: space[10], paddingVertical: space[8] }}>
          <TopBar title="Edit and approve" sub="Saving puts it in the level you pick." />
          <Field label="Title" value={title} onChangeText={setTitle} error={errors.title} maxLength={140} />
          <Field label="What the child should do" value={instructions} onChangeText={setInstructions} error={errors.instructions} lines={2} maxLength={500} />
          <Field label="The words the child reads" value={passage} onChangeText={setPassage} error={errors.passage_text} lines={5} maxLength={6000} hint="Change the text and the app checks the child's reading against these exact words." />
          <View style={{ gap: space[6] }}>
            <Small>Level</Small>
            <Chips label="Level" options={['Easy', 'Medium', 'Hard']} picked={level ? [level] : []} onToggle={(l) => setLevel(l as api.Level)} />
          </View>
          {problem ? <Notice text={problem} /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

// ------------------------------------------------------------------ assign

type Kind = 'class' | 'group' | 'learner';

const FIT_TONE = { ok: 'green', caution: 'amber', blocked: 'red', unknown: 'grey' } as const;

function AssignView({ a, busy, problem, onBack, onGive }: { a: api.ActivityPage; busy: boolean; problem: string | null; onBack: () => void; onGive: (target: { learner_id?: number; class_id?: number; group_tag?: string }) => void }) {
  const t = a.targets;
  const [kind, setKind] = useState<Kind>('class');
  const [picked, setPicked] = useState<string | null>(null);

  const options: { key: string; title: string; sub?: string; fit: api.Fit }[] =
    kind === 'class'
      ? (t?.classes ?? []).map((c) => ({ key: String(c.id), title: c.name, sub: c.grade, fit: c.fit }))
      : kind === 'group'
        ? (t?.groups ?? []).map((g) => ({ key: g.tag, title: g.tag, sub: 'Classes with this focus', fit: g.fit }))
        : (t?.learners ?? []).map((l) => ({ key: String(l.id), title: l.name, sub: l.className ?? undefined, fit: l.fit }));

  const chosen = options.find((o) => o.key === picked) ?? null;
  const blocked = chosen?.fit.verdict === 'blocked';

  function give() {
    if (!chosen || blocked) return;
    onGive(kind === 'class' ? { class_id: Number(chosen.key) } : kind === 'group' ? { group_tag: chosen.key } : { learner_id: Number(chosen.key) });
  }

  return (
    <Screen withTabBar header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />} footer={<Btn label={chosen ? `Give to ${chosen.title}` : 'Pick who gets it'} onPress={give} disabled={!chosen || blocked} loading={busy} />}>
      <View style={{ gap: space[12], paddingTop: space[8] }}>
        <TopBar title="Give this activity" sub={a.title} />
        <Notice tone="info" text={`One grade only. This is a ${a.grade} activity, so it can only go to ${a.grade} classes and learners, even if you handle more than one grade.`} />
        <Segmented
          options={[
            { key: 'class', label: 'Class' },
            { key: 'group', label: 'Focus group' },
            { key: 'learner', label: 'Learner' },
          ]}
          value={kind}
          onChange={(k) => {
            setKind(k);
            setPicked(null);
          }}
        />

        {options.length === 0 ? (
          <P style={{ textAlign: 'center', paddingVertical: space[16] }}>
            {kind === 'group' ? 'None of your classes has a focus group. Add one when you edit a class.' : kind === 'class' ? 'You have no class this school year.' : 'There are no learners in your classes yet.'}
          </P>
        ) : (
          options.map((o) => {
            const on = o.key === picked;
            return (
              <Pressable
                key={o.key}
                accessibilityRole="radio"
                accessibilityState={{ selected: on }}
                accessibilityLabel={`${o.title}${o.fit.verdict === 'blocked' ? (o.fit.reason === 'grade' ? ', another grade' : ', too long for them') : ''}`}
                onPress={() => setPicked(o.key)}
                style={[st.option, on && st.optionOn]}
              >
                <View style={{ flex: 1, gap: space[2] }}>
                  <Text maxFontSizeMultiplier={1.25} style={st.optionTitle}>
                    {o.title}
                  </Text>
                  {o.sub ? <Small>{o.sub}</Small> : null}
                  {o.fit.note ? <Small style={{ color: o.fit.verdict === 'blocked' ? colors.bad : colors.warn }}>{o.fit.note}</Small> : null}
                </View>
                {o.fit.verdict !== 'ok' && o.fit.verdict !== 'unknown' ? <TonePill text={o.fit.verdict === 'blocked' ? (o.fit.reason === 'grade' ? 'Other grade' : 'Too long') : 'A stretch'} tone={FIT_TONE[o.fit.verdict]} /> : null}
              </Pressable>
            );
          })
        )}
        {problem ? <Notice text={problem} /> : null}
      </View>
    </Screen>
  );
}

// ------------------------------------------------------------------ share

function ShareView({ a, busy, problem, onBack, onShare }: { a: api.ActivityPage; busy: boolean; problem: string | null; onBack: () => void; onShare: (b: { price_type: 'Free' | 'Paid'; price?: number }) => void }) {
  const [type, setType] = useState<'Free' | 'Paid'>('Free');
  const [price, setPrice] = useState('');
  const [error, setError] = useState<string | null>(null);

  function share() {
    if (type === 'Paid') {
      const n = Number(price);
      if (!price.trim() || !Number.isFinite(n) || n <= 0) {
        setError('Type the price in pesos, for example 25.');
        return;
      }
      onShare({ price_type: 'Paid', price: n });
    } else {
      onShare({ price_type: 'Free' });
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
      <Screen withTabBar anchor="content" header={<IconBtn icon="caret-left" label="Back" onPress={onBack} />} footer={<Btn label="Share to the Repository" onPress={share} loading={busy} />}>
        <View style={{ gap: space[12], paddingVertical: space[8] }}>
          <TopBar title="Share to the Repository" sub={a.title} />
          <P>Parents can find this activity and unlock it for their child. You earn 2 free credits for sharing. An activity can be shared once.</P>
          <Segmented
            options={[
              { key: 'Free', label: 'Free' },
              { key: 'Paid', label: 'Paid' },
            ]}
            value={type}
            onChange={(k) => {
              setType(k);
              setError(null);
            }}
          />
          {type === 'Paid' ? (
            <Field label="Price in pesos" value={price} onChangeText={(t) => (setError(null), setPrice(t))} keyboardType="numeric" placeholder="25" error={error} hint="Payments are simulated for now. No real money moves." />
          ) : null}
          {problem ? <Notice text={problem} /> : null}
        </View>
      </Screen>
    </KeyboardAvoidingView>
  );
}

const st = StyleSheet.create({
  pills: { flexDirection: 'row', flexWrap: 'wrap', gap: space[6] },
  passage: { fontFamily: fonts.read, fontSize: s(18), lineHeight: s(28), color: colors.ink },
  levels: { flexDirection: 'row', gap: space[8] },
  levelBtn: { flex: 1, minWidth: 0, minHeight: size.button, borderRadius: s(14), backgroundColor: colors.sky, alignItems: 'center', justifyContent: 'center' },
  levelText: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.blueDark },
  option: { flexDirection: 'row', alignItems: 'center', gap: space[10], minHeight: s(60), backgroundColor: colors.white, borderRadius: s(16), borderWidth: 2, borderColor: colors.line, paddingHorizontal: space[12], paddingVertical: space[10] },
  optionOn: { borderColor: colors.blue, backgroundColor: colors.sky },
  optionTitle: { fontFamily: fonts.uiBold, fontSize: s(18), lineHeight: s(23), color: colors.ink },
});
