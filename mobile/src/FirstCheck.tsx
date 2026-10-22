import { useEffect, useState } from 'react';
import { View } from 'react-native';
import * as Speech from 'expo-speech';
import * as api from './api';
import { Feedback } from './Feedback';
import { MicButton } from './MicButton';
import { ReadingScene } from './ReadingScene';
import { Animation, Btn, H1, IconBtn, Notice, P, Screen } from './ui';
import { WarmUp } from './WarmUp';
import { SendFailed, Waiting } from './Waiting';
import { s, SHORT_HEIGHT } from './scale';
import { space } from './theme';

type Phase =
  | { name: 'intro'; error?: string }
  | { name: 'loading' }
  | { name: 'warm'; item: api.DiagnosticPassage }
  | { name: 'item'; item: api.DiagnosticPassage }
  | { name: 'sending'; item: api.DiagnosticPassage; uri: string }
  | { name: 'failed'; item: api.DiagnosticPassage; uri: string; message: string }
  | { name: 'next'; message: string }
  | { name: 'unclear'; item: api.DiagnosticPassage; final: boolean }
  | { name: 'done'; outcome: Extract<api.CheckOutcome, { status: 'finished' }> };

const WELCOME = 'Tara will pick the best story for you. Say the words out loud when you are ready.';

/**
 * The first reading check. Every new child does it once; it places them on the reading path. No score is shown, so
 * nobody feels tested. Up to three short items, each one a recording scored by the server.
 */
export function FirstCheck({ firstName, onDone }: { firstName: string; onDone: () => void }) {
  const [phase, setPhase] = useState<Phase>({ name: 'intro' });
  // The warm-up (say one easy word to test the microphone) happens once, before the very first item.
  const [warmedUp, setWarmedUp] = useState(false);

  // Wake the scoring service while the child reads the welcome, so the first recording is not slow.
  useEffect(() => {
    api.warm().catch(() => {});
    return () => void Speech.stop();
  }, []);

  async function loadItem() {
    setPhase({ name: 'loading' });
    try {
      const item = await api.firstCheckPassage();
      setPhase(!warmedUp && item.passageNumber === 1 ? { name: 'warm', item } : { name: 'item', item });
    } catch (e) {
      setPhase({ name: 'intro', error: e instanceof Error ? e.message : 'Something went wrong. Please try again.' });
    }
  }

  async function send(item: api.DiagnosticPassage, uri: string) {
    setPhase({ name: 'sending', item, uri });
    try {
      const outcome = await api.firstCheckRecord(uri);
      if (outcome.status === 'finished') setPhase({ name: 'done', outcome });
      else if (outcome.status === 'continue') setPhase({ name: 'next', message: outcome.message });
      else setPhase({ name: 'unclear', item, final: outcome.final });
    } catch (e) {
      setPhase({ name: 'failed', item, uri, message: e instanceof Error ? e.message : 'Something went wrong.' });
    }
  }

  switch (phase.name) {
    case 'intro':
    case 'loading':
      return (
        <Screen
          anchor="content"
          footer={
            <View style={{ flexDirection: 'row', gap: space[10], alignItems: 'center' }}>
              <IconBtn icon="speaker-high" label="Read it to me" onPress={() => Speech.speak(`Let's read together, ${firstName}! ${WELCOME}`, { language: 'en-US', rate: 0.85 })} />
              <Btn label="I'm Ready" style={{ flex: 1 }} onPress={loadItem} loading={phase.name === 'loading'} />
            </View>
          }
        >
          <View style={{ alignItems: 'center', gap: space[12] }}>
            <Animation name="intro" px={SHORT_HEIGHT ? s(120) : s(185)} />
            <H1 style={{ textAlign: 'center' }}>Let's Read Together, {firstName}!</H1>
            <P style={{ textAlign: 'center' }}>{WELCOME}</P>
            {phase.name === 'intro' && phase.error ? <Notice text={phase.error} /> : null}
          </View>
        </Screen>
      );

    case 'warm':
      return (
        <WarmUp
          letters={phase.item.activity.kind === 'letters'}
          onDone={() => {
            setWarmedUp(true);
            setPhase({ name: 'item', item: phase.item });
          }}
        />
      );

    case 'item':
      return (
        <ReadingScene
          eyebrow={phase.item.activity.kind === 'letters' ? 'Letters' : `Passage ${phase.item.passageNumber}`}
          // The check stops as soon as it knows the child's level, so the dots never promise a total: none on the
          // first item, then one dot per item reached so far (the same as the website).
          dots={phase.item.passageNumber > 1 ? { done: phase.item.passageNumber - 1, total: phase.item.passageNumber } : undefined}
          direction={phase.item.activity.prompt}
          passage={
            phase.item.activity.kind === 'letters'
              ? { kind: 'letters', letters: phase.item.activity.letters ?? [] }
              : { kind: 'text', text: phase.item.activity.passageText }
          }
          speakText={phase.item.activity.kind === 'letters' ? undefined : phase.item.activity.passageText}
          footer={
            <MicButton
              idleLabel={phase.item.activity.kind === 'letters' ? 'Tap the mic and say the letters' : 'Tap the mic and read the words'}
              onRecorded={(uri) => send(phase.item, uri)}
            />
          }
        />
      );

    case 'sending':
      return <Waiting />;

    case 'failed':
      return <SendFailed message={phase.message} onRetry={() => send(phase.item, phase.uri)} onReread={() => setPhase({ name: 'item', item: phase.item })} />;

    case 'next':
      return <Feedback mood="happy" heading="Great job!" message={phase.message} buttonLabel="Keep Going" onButton={loadItem} />;

    case 'unclear':
      return (
        <Feedback
          mood="sad"
          heading={phase.final ? "Let's ask for help" : "Didn't quite catch that!"}
          message={
            phase.final
              ? 'Please ask a grown up to sit with you, then try again.'
              : 'Find a quiet spot, hold the phone a little closer, and read it again.'
          }
          buttonLabel="Try Again"
          onButton={() => setPhase({ name: 'item', item: phase.item })}
        />
      );

    case 'done':
      return (
        <Feedback
          mood="happy"
          heading={`You did it, ${firstName}!`}
          level={phase.outcome.resultLabel}
          badges={phase.outcome.newBadges}
          buttonLabel="Start Exploring"
          onButton={onDone}
        />
      );
  }
}
