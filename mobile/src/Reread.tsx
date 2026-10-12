import { useEffect, useState } from 'react';
import { View } from 'react-native';
import * as api from './api';
import { Feedback, type Stat } from './Feedback';
import { MicButton } from './MicButton';
import { ReadingScene } from './ReadingScene';
import { useSession } from './session';
import { Animation, Btn, Busy, Notice, Panel, Screen, Small } from './ui';
import { SendFailed, Waiting } from './Waiting';
import { WordReview } from './WordReview';
import { colors } from './theme';

type Scored = Extract<api.RereadOutcome, { status: 'scored' }>;

type Phase =
  | { name: 'loading' }
  | { name: 'error'; message: string }
  | { name: 'read' }
  | { name: 'sending'; uri: string }
  | { name: 'failed'; uri: string; message: string }
  | { name: 'unclear' }
  | { name: 'done'; outcome: Scored };

/**
 * Read a shelf story again for fun. Scored for feedback only: nothing is saved, so there are no points, no streak, no level and
 * no limit on tries. The screens say so, so a child never wonders whether this one counts.
 */
export function Reread({ id, onExit }: { id: number; onExit: () => void }) {
  const { learner } = useSession();
  const [book, setBook] = useState<api.BookDetail | null>(null);
  const [phase, setPhase] = useState<Phase>({ name: 'loading' });

  useEffect(() => {
    api.warm().catch(() => {});
    (async () => {
      try {
        setBook(await api.bookDetail(id));
        setPhase({ name: 'read' });
      } catch (e) {
        setPhase({ name: 'error', message: e instanceof Error ? e.message : 'Something went wrong.' });
      }
    })();
  }, [id]);

  async function send(uri: string) {
    setPhase({ name: 'sending', uri });
    try {
      const outcome = await api.rereadBook(id, uri);
      setPhase(outcome.status === 'unclear' ? { name: 'unclear' } : { name: 'done', outcome });
    } catch (e) {
      setPhase({ name: 'failed', uri, message: e instanceof Error ? e.message : 'Something went wrong.' });
    }
  }

  if (phase.name === 'loading') {
    return (
      <Screen scroll={false}>
        <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 14 }}>
          <Animation name="head" px={130} />
          <Busy text="Opening your story" />
        </View>
      </Screen>
    );
  }

  if (phase.name === 'error' || !book) {
    return (
      <Screen footer={<Btn label="Back to My Shelf" tone="ghost" onPress={onExit} />}>
        <View style={{ flex: 1, justifyContent: 'center' }}>
          <Notice text={phase.name === 'error' ? phase.message : 'This story could not be opened.'} />
        </View>
      </Screen>
    );
  }

  if (phase.name === 'sending') return <Waiting />;

  if (phase.name === 'failed') {
    return <SendFailed message={phase.message} onRetry={() => send(phase.uri)} onReread={() => setPhase({ name: 'read' })} />;
  }

  if (phase.name === 'unclear') {
    return (
      <Feedback
        mood="sad"
        heading="Didn't quite catch that!"
        message="Find a quiet spot, hold the phone a little closer, and read it again."
        buttonLabel="Try Again"
        onButton={() => setPhase({ name: 'read' })}
        second={{ label: 'Back to My Shelf', onPress: onExit }}
      />
    );
  }

  if (phase.name === 'done') {
    const counts = phase.outcome.wordCounts;
    const stats: Stat[] = counts
      ? [{ value: `${counts.right} of ${counts.total}`, label: 'Words read right', icon: 'target', tone: colors.ok }]
      : [];
    return (
      <Feedback
        mood="happy"
        heading={`Nice reading, ${learner.firstName}!`}
        subline={`You read "${book.title}" again. This one was just for fun, so nothing was saved.`}
        stats={stats}
        buttonLabel="Read It Again"
        onButton={() => setPhase({ name: 'read' })}
        second={{ label: 'Back to My Shelf', onPress: onExit }}
        details={
          <Panel style={{ gap: 8 }}>
            <Small>Your words</Small>
            <WordReview words={phase.outcome.wordBreakdown} extra={phase.outcome.extraWordsSaid} />
          </Panel>
        }
      />
    );
  }

  return (
    <ReadingScene
      eyebrow={book.title}
      pill="Free practice. Nothing is saved."
      direction="Read it out loud again. This one is just for fun."
      passage={{ kind: 'text', text: book.passageText }}
      speakText={book.passageText}
      footer={<MicButton idleLabel="Tap the mic and read the story" onRecorded={(uri) => send(uri)} />}
      onBack={onExit}
    />
  );
}
