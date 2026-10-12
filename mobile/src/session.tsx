import { createContext, useContext } from 'react';
import type * as api from './api';

/** What every signed in screen can ask for: who is signed in, their Home numbers, and how to sign out. */
export type Session = {
  learner: api.LearnerPayload;
  /** Home's numbers (reading path, weekly goal, badges...). Null until the first load. */
  home: api.Home | null;
  homeError: string | null;
  reloadHome: () => Promise<void>;
  /** The child's reading text size, 1 to 5. Saved on the server so the website shows the same. */
  fontStep: number;
  setFontStep: (step: number) => void;
  /** Update what we know about the child after a reading changes it (points, step, streak). */
  setLearner: (learner: api.LearnerPayload) => void;
  signOut: () => void;
};

export const SessionContext = createContext<Session | null>(null);

export function useSession(): Session {
  const value = useContext(SessionContext);
  if (!value) throw new Error('useSession must be used inside the signed in app.');
  return value;
}
