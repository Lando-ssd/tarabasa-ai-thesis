import type { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import * as api from '../api';
import { Notice, H2, P, Panel, Screen, Small } from '../ui';
import { Initial, Loading, TonePill, TopBar, useLoad } from './kit';
import { colors, fonts, space } from '../theme';
import { s } from '../scale';

type Props = { classId: number; id: number; onBack: () => void };

/**
 * One learner, for the teacher: where the child reads now, what the parent shared, what suits them and how to teach it. The
 * website's learner window, in one column. Every line is worked out on the server, the same way the website works it out.
 */
export function LearnerScreen({ classId, id, onBack }: Props) {
  const { data, error, reload } = useLoad(() => api.teacherLearner(classId, id), [classId, id]);

  if (!data) {
    return (
      <Screen withTabBar>
        <TopBar title="Learner" onBack={onBack} />
        <Loading error={error} onRetry={reload} text="Getting the learner" />
      </Screen>
    );
  }

  const { learner: l, profile: p } = data;
  const first = l.firstName;

  return (
    <Screen withTabBar>
      <TopBar title={l.name} sub={[l.grade, l.code].filter(Boolean).join(' · ')} onBack={onBack} right={<Initial name={first} px={s(52)} />} />

      <View style={{ gap: space[14] }}>
        <View style={{ flexDirection: 'row', gap: space[8], flexWrap: 'wrap' }}>
          <TonePill text={l.level} tone={l.level === 'Non-reader' || l.level === 'Frustration' ? 'amber' : l.level === 'Independent' ? 'green' : 'blue'} />
          {l.step ? <TonePill text={l.step} tone="grey" /> : null}
          {l.tag ? <TonePill text={l.tag.label} tone={l.tag.tone} /> : null}
        </View>

        {l.levelCheck ? <Notice tone="info" text={l.levelCheck.text} /> : null}

        <Section title={`Where ${first} reads now`}>
          {p.check.done ? (
            <>
              <Line label="Reading check" value={`Done. ${first} is a ${p.check.step}.`} />
              {p.check.band ? <Line label="Phil-IRI level" value={p.check.band} /> : null}
              {p.progress ? (
                <Line
                  label="Toward the next step"
                  value={p.progress.atTop ? 'At the top step.' : `${p.progress.have} of ${p.progress.need} great readings counted toward ${p.progress.nextRungLabel ?? 'the next step'}.`}
                />
              ) : null}
            </>
          ) : (
            <P>{first} has not done the first reading check yet.{p.startsAt ? ` It will start at ${p.startsAt}, from what the parent shared.` : ''}</P>
          )}
          {p.mismatch ? <Notice tone="info" text={p.mismatch} /> : null}
        </Section>

        <Section title="What the parent shared">
          {p.stage || p.language || p.supports.length || p.interests.length || p.answers.length ? (
            <>
              {p.stage ? <Line label="Reading, in the parent's words" value={p.stage} /> : null}
              {p.language ? <Line label="Home language" value={p.language} /> : null}
              {p.supports.length ? <Line label="What helps" value={p.supports.join(', ')} /> : null}
              {p.interests.length ? <Line label="Likes" value={p.interests.join(', ')} /> : null}
              {p.answers.map((a) => (
                <Line key={a.question} label={a.question} value={a.answer} />
              ))}
            </>
          ) : (
            <P>The parent did not share more yet.</P>
          )}
        </Section>

        <Section title={`What suits ${first} now`}>
          <Line label="Step" value={`${p.bestFit.step}: ${p.bestFit.skill}`} />
          <Line label="Activity level" value={p.bestFit.level} />
          <Line label="Length" value={p.bestFit.words} />
          <P>{p.bestFit.advice}</P>
          <Small>{p.bestFit.code}</Small>
        </Section>

        <Section title="How to teach it">
          <P>{p.teach.focus}</P>
          {p.teach.moves.map((m) => (
            <View key={m} style={st.bullet}>
              <View style={st.dot} />
              <P style={{ flex: 1 }}>{m}</P>
            </View>
          ))}
          <Line label="Ready for the next step when" value={p.teach.ready} />
          <Line label="Watch for" value={p.teach.watch} />
          <Small>{p.teach.basis}</Small>
        </Section>

        {p.pattern ? (
          <Section title="What the readings show">
            <P>
              {first} {p.pattern.says}.{p.pattern.early ? ' This is early: it rests on only a few readings.' : ''}
            </P>
            {p.pattern.examples.length ? <Line label="For example" value={p.pattern.examples.join(', ')} /> : null}
            <Line label="One thing to try" value={p.pattern.tip} />
            <Small>
              {p.pattern.code}: {p.pattern.codeText}
            </Small>
          </Section>
        ) : null}
      </View>
    </Screen>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <Panel style={{ gap: space[8] }}>
      <H2>{title}</H2>
      {children}
    </Panel>
  );
}

function Line({ label, value }: { label: string; value: string }) {
  return (
    <View style={{ gap: space[2] }}>
      <Small>{label}</Small>
      <Text maxFontSizeMultiplier={1.25} style={st.value}>
        {value}
      </Text>
    </View>
  );
}

const st = StyleSheet.create({
  value: { fontFamily: fonts.ui, fontSize: s(17), lineHeight: s(23), color: colors.ink },
  bullet: { flexDirection: 'row', gap: space[8], alignItems: 'flex-start' },
  dot: { width: s(8), height: s(8), borderRadius: s(4), backgroundColor: colors.orange, marginTop: s(8) },
});
