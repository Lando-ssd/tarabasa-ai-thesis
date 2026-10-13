import { useState } from 'react';
import * as api from './api';
import { MicButton } from './MicButton';
import type { SayResult } from './SayWord';
import { Busy } from './ui';

/**
 * Read a row of words or letters, or a sentence, out loud into the microphone, for the games. One tap, one recording that
 * stops by itself when the child pauses (or when they tap the check mark), never a live stream. The phone first checks that
 * the microphone heard a voice at all (nothing is sent if it did not: the child is just asked again, and that does not use up
 * a try). The clip goes to the same service that scores readings, which answers only "heard" or "again" (and which of the
 * words were heard), never "wrong", and keeps nothing. A microphone that is blocked, missing or unreachable answers
 * "unavailable", and the game carries on without the speaking part.
 */
export function SayText({
  text,
  kind,
  idleLabel,
  onResult,
}: {
  /** What the child is asked to read, lowercase words separated by spaces. */
  text: string;
  kind: api.ReadKind;
  idleLabel: string;
  onResult: (result: SayResult, matched: boolean[]) => void;
}) {
  const [checking, setChecking] = useState(false);
  const words = text.trim().split(/\s+/).length;
  // A short start, then a little under two seconds a word, never more than eighteen seconds in all.
  const maxSeconds = Math.min(18, Math.round(3.5 + 1.8 * words));

  async function send(uri: string) {
    setChecking(true);
    try {
      const { status, matched } = await api.checkText(text, kind, uri);
      onResult(status, matched ?? []);
    } catch {
      // The service could not be reached: the game goes on without the speaking part.
      onResult('unavailable', []);
    } finally {
      setChecking(false);
    }
  }

  if (checking) return <Busy text="Tara is checking" />;
  return (
    <MicButton
      idleLabel={idleLabel}
      maxSeconds={maxSeconds}
      stopOnPause
      silentHint="We did not hear anything. Hold the phone a little closer and read it out loud."
      onRecorded={(uri) => send(uri)}
    />
  );
}
