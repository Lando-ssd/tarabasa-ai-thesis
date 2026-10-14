import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import * as api from './api';
import { Feedback } from './Feedback';
import { MicButton } from './MicButton';
import { ReadingScene } from './ReadingScene';
import { Animation, Btn, Busy, H2, IconBtn, Notice, Panel, Screen, Small } from './ui';
import { SendFailed, Waiting } from './Waiting';
import { WordReview } from './WordReview';
import { colors, fonts, space } from './theme';
import { s } from './scale';

type Scored = Extract<api.ReadingOutcome, { status: 'scored' }>;
type PracticeScored = Extract<api.PracticeOutcome, { status: 'scored' }>;

type Phase =
  | { name: 'loading' }
  | { name: 'error'; message: string }
  | { name: 'read' }
  | { name: 'quiz'; uri: string }
  | { name: 'sending'; kind: 'practice' | 'real'; uri: string; answers?: string[] }
  | { name: 'failed'; kind: 'practice' | 'real'; uri: string; answers?: string[]; message: string }
  | { name: 'unclear'; kind: 'practice' | 'real'; final: boolean };

const STAGES = ['Listen', 'Your turn', 'Read for real'];

/**
 * One story, in the same three stages as the website: Tara reads it first, two free practice tries (scored for feedback
 * only, nothing is saved), then the real reading, which counts. A comprehension story asks its questions after the
 * reading, and the answers are checked on the server, never on the phone.
 */
export function Story({ id, onExit, onScored }: { id: number; onExit: () => void; onScored: (title: string, outcome: Scored) => void }) {
  const [detail, setDetail] = useState<api.ActivityDetail | null>(null);
  const [phase, setPhase] = useState<Phase>({ name: 'loading' });
  const [stage, setStage] = useState<0 | 1 | 2>(0);
  const [triesLeft, setTriesLeft] = useState(2);
  const [feedback, setFeedback] = useState<PracticeScored | null>(null);

  useEffect(() => {
    api.warm().catch(() => {});
    (async () => {
      try {
        const d = await api.activityDetail(id);
        setDetail(d);
        setTriesLeft(d.practice.triesLeft);
        setStage(d.practice.startAt === 'listen' ? 0 : d.practice.startAt === 'try' ? 1 : 2);
        setPhase({ name: 'read' });
      } catch (e) {
        setPhase({ name: 'error', message: e instanceof Error ? e.message : 'Something went wrong.' });
      }
    })();
  }, [id]);

  async function send(kind: 'practice' | 'real', uri: string, answers?: string[]) {
    if (!detail) return;
    setPhase({ name: 'sending', kind, uri, answers });
    try {
      if (kind === 'practice') {
        const r = await api.practiceTry(detail.id, uri);
        if (r.status === 'no_tries_left') {
          setTriesLeft(0);
          setStage(2);
          setPhase({ name: 'read' });
        } else if (r.status === 'unclear') {
          setTriesLeft(r.triesLeft);
          setPhase({ name: 'unclear', kind, final: false });
        } else {
          setFeedback(r);
          setTriesLeft(r.triesLeft);
          setPhase({ name: 'read' });
        }
      } else {
        const r = await api.realReading(detail.id, uri, answers);
        if (r.status === 'unclear') setPhase({ name: 'unclear', kind, final: r.final });
        else onScored(detail.title, r);
      }
    } catch (e) {
      setPhase({ name: 'failed', kind, uri, answers, message: e instanceof Error ? e.message : 'Something went wrong.' });
    }
  }

  if (phase.name === 'loading') {
    return (
      <Screen scroll={false}>
        <View style={styles.center}>
          <Animation name="head" px={s(130)} />
          <Busy text="Opening your story" />
        </View>
      </Screen>
    );
  }

  if (phase.name === 'error' || !detail) {
    return (
      <Screen footer={<Btn label="Back" tone="ghost" onPress={onExit} />}>
        <View style={styles.center}>
          <Notice text={phase.name === 'error' ? phase.message : 'This story could not be opened.'} />
        </View>
      </Screen>
    );
  }

  if (phase.name === 'sending') return <Waiting title={phase.kind === 'practice' ? 'Checking your practice' : 'Checking your reading'} />;

  if (phase.name === 'failed') {
    return (
      <SendFailed
        message={phase.message}
        onRetry={() => send(phase.kind, phase.uri, phase.answers)}
        onReread={() => setPhase({ name: 'read' })}
      />
    );
  }

  if (phase.name === 'unclear') {
    return (
      <Feedback
        mood="sad"
        heading={phase.final ? "Let's ask for help" : "Didn't quite catch that!"}
        message={phase.final ? 'Please ask a grown up to sit with you, then try again.' : 'Find a quiet spot, hold the phone a little closer, and read it again.'}
        buttonLabel="Try Again"
        onButton={() => setPhase({ name: 'read' })}
      />
    );
  }

  if (phase.name === 'quiz') {
    return (
      <Quiz
        questions={detail.quizQuestions}
        onBack={() => setPhase({ name: 'read' })}
        onSubmit={(answers) => send('real', phase.uri, answers)}
      />
    );
  }

  // The reading screen itself.
  const passage = { kind: 'text' as const, text: detail.passageText };
  const finishReal = (uri: string) => (detail.quizQuestions.length > 0 ? setPhase({ name: 'quiz', uri }) : send('real', uri));

  const goReal = () => {
    setStage(2);
  };

  let direction: string;
  let footer: React.ReactNode;
  let below: React.ReactNode = null;

  if (stage === 0) {
    direction = 'Listen first. Tap the speaker and Tara reads the story to you.';
    footer = <Btn label="My Turn" onPress={() => setStage(triesLeft > 0 ? 1 : 2)} />;
  } else if (stage === 1) {
    direction =
      triesLeft > 0
        ? `Your turn. Read it out loud. This is a free try and nothing is saved. ${triesLeft} ${triesLeft === 1 ? 'try' : 'tries'} left.`
        : 'You used both free tries. Now read it for real.';
    below = feedback ? <PracticeFeedback result={feedback} /> : null;
    footer = (
      <View style={{ gap: space[10] }}>
        {triesLeft > 0 ? <MicButton idleLabel={feedback ? 'Tap the mic to try again' : 'Tap the mic and read the story'} onRecorded={(uri) => send('practice', uri)} /> : null}
        <Btn label="Read For Real" tone={feedback || triesLeft === 0 ? 'orange' : 'ghost'} compact={triesLeft > 0 && !feedback} onPress={goReal} />
      </View>
    );
  } else {
    direction = 'Now read it for real. This one counts, and you can do it.';
    footer = <MicButton idleLabel="Tap the mic and read the story" onRecorded={finishReal} />;
  }

  return (
    <ReadingScene
      eyebrow={detail.title}
      steps={{ labels: STAGES, active: stage }}
      direction={direction}
      passage={passage}
      speakText={detail.passageText}
      below={below}
      footer={footer}
      onBack={onExit}
    />
  );
}

export function PracticeFeedback({ result }: { result: PracticeScored }) {
  const counts = result.wordCounts;
  return (
    <Panel style={{ gap: space[10] }}>
      <H2>Your practice</H2>
      <Small style={{ color: colors.ink }}>
        {counts ? `${counts.right} of ${counts.total} words read right. ` : ''}This one is not saved. Look at the words, then try again or read for real.
      </Small>
      <WordReview words={result.wordBreakdown} extra={result.extraWordsSaid} />
    </Panel>
  );
}

/** The comprehension questions, one big tile per choice. The right answer is never on the phone. */
export function Quiz({ questions, onBack, onSubmit }: { questions: api.ActivityDetail['quizQuestions']; onBack: () => void; onSubmit: (answers: string[]) => void }) {
  const [picked, setPicked] = useState<Record<number, string>>({});
  const done = questions.every((_, i) => picked[i] !== undefined);
  return (
    <Screen
      footer={<Btn label="Submit My Answers" onPress={() => onSubmit(questions.map((_, i) => picked[i]))} disabled={!done} accessibilityHint={done ? undefined : 'Answer every question first'} />}
    >
      <View style={styles.quizTop}>
        <IconBtn icon="caret-left" label="Go back" onPress={onBack} />
        <Text maxFontSizeMultiplier={1.2} style={styles.quizTitle}>
          A few questions
        </Text>
        <View style={{ width: s(48) }} />
      </View>
      <View style={{ gap: space[16], marginTop: space[8] }}>
        {questions.map((q, i) => (
          <View key={i} style={{ gap: space[8] }}>
            <Text maxFontSizeMultiplier={1.2} style={styles.question}>
              {i + 1}. {q.question}
            </Text>
            {q.choices.map((choice) => {
              const on = picked[i] === choice;
              return (
                <Pressable
                  key={choice}
                  accessibilityRole="radio"
                  accessibilityState={{ selected: on }}
                  onPress={() => setPicked({ ...picked, [i]: choice })}
                  style={[styles.choice, on && styles.choiceOn]}
                >
                  <View style={[styles.radio, on && styles.radioOn]} />
                  <Text maxFontSizeMultiplier={1.2} style={styles.choiceText}>
                    {choice}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        ))}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: space[14] },
  quizTop: { flexDirection: 'row', alignItems: 'center', gap: space[10] },
  quizTitle: { flex: 1, textAlign: 'center', fontFamily: fonts.displayMedium, fontSize: s(22), color: colors.ink },
  question: { fontFamily: fonts.read, fontSize: s(19), lineHeight: s(28), color: colors.ink },
  choice: { minHeight: s(56), flexDirection: 'row', alignItems: 'center', gap: space[12], backgroundColor: colors.white, borderWidth: 2, borderColor: colors.line, borderRadius: s(16), paddingHorizontal: space[14], paddingVertical: space[10] },
  choiceOn: { borderColor: colors.orange, backgroundColor: colors.orangeSoft },
  choiceText: { flex: 1, fontFamily: fonts.read, fontSize: s(18), lineHeight: s(26), color: colors.ink },
  radio: { width: s(24), height: s(24), borderRadius: s(12), borderWidth: 2, borderColor: colors.line, backgroundColor: colors.white },
  radioOn: { borderColor: colors.orange, backgroundColor: colors.orange },
});
