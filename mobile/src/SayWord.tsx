import { useState } from 'react';
import * as api from './api';
import { MicButton } from './MicButton';
import { Busy } from './ui';

export type SayResult = 'heard' | 'again' | 'unavailable';

/**
 * Say one word into the microphone, for the games. One tap, about three seconds, one word, never a live stream. The phone
 * first checks that the microphone heard a voice at all (nothing is sent if it did not: the child is just asked again, and
 * that does not use up a try). The clip goes to the same service that scores readings, which answers only "heard" or "again",
 * never "wrong", and keeps nothing. A microphone that is blocked, missing or unreachable answers "unavailable", and the game
 * carries on without the speaking part.
 */
export function SayWord({ word, idleLabel = 'Tap the mic and say the word', onResult }: { word: string; idleLabel?: string; onResult: (result: SayResult) => void }) {
  const [checking, setChecking] = useState(false);

  async function send(uri: string) {
    setChecking(true);
    try {
      const { status } = await api.checkWord(word, uri);
      onResult(status);
    } catch {
      // The service could not be reached: the game goes on without the speaking part.
      onResult('unavailable');
    } finally {
      setChecking(false);
    }
  }

  if (checking) return <Busy text="Tara is checking" />;
  return (
    <MicButton
      idleLabel={idleLabel}
      maxSeconds={3}
      silentHint="We did not hear anything. Hold the phone a little closer and say the word out loud."
      onRecorded={(uri) => send(uri)}
    />
  );
}
