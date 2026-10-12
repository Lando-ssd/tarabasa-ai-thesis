import { Platform } from 'react-native';
import { API_URL, REQUEST_TIMEOUT_MS, UPLOAD_TIMEOUT_MS } from './config';
import * as storage from './storage';

const TOKEN_KEY = 'tarabasa_token';

// Set once by the app: called when the server says the saved sign in is no longer good (a 401), so the app can go back to sign in.
let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public body: unknown = null,
  ) {
    super(message);
  }
}

export type LearnerPayload = {
  id: number;
  learnerCode: string;
  firstName: string;
  gradeLevel: string | null;
  avatarId: string | null;
  avatarPhotoUrl: string | null;
  masteryLevel: string;
  points: number;
  /** The old stored counter (one per scored reading). The number shown as days in a row is dayStreak. */
  streak: number;
  dayStreak: number;
  readingFontStep: number;
  themeColor: 'blue' | 'pink' | string;
  /** The four step path: 1 Letter Explorer to 4 Story Reader. step is null before the first check. */
  readingStep: { step: number | null; name: string };
};

export type LoginResult = {
  token: string;
  needsDiagnostic: boolean;
  learner: LearnerPayload;
};

export type Home = {
  learner: LearnerPayload;
  readingPath: { step: number; name: string; skill: string; blurb: string; isCurrent: boolean }[];
  competencyProgress: unknown[];
  streak: { days: number; week: { label: string; done: boolean }[] };
  weeklyGoal: { done: number; target: number; met: boolean };
  growth: { days: { label: string; count: number; isToday: boolean; isFuture: boolean }[]; scale: number };
  badges: Badge[];
};

export type Badge = {
  code: string;
  name: string;
  description: string;
  /** The group it belongs to, such as Reading or Streaks. */
  category: string;
  /** A Phosphor icon name, the same icon the website shows. */
  icon: string;
  earned: boolean;
  earnedAt: string | null;
  comingSoon: boolean;
  /** How far the child is toward it, when that can be counted. */
  current: number | null;
  target: number | null;
};

export type NewBadge = { code: string; name: string; description: string; icon: string };

/** One word of the passage, after scoring. */
export type WordResult = {
  text: string;
  status: 'correct' | 'repeated' | 'skip' | 'sub' | 'mispronounced' | 'unsure';
  heard: string | null;
};

export type PracticeOutcome =
  | { status: 'no_tries_left'; triesLeft: 0 }
  | { status: 'unclear'; triesLeft: number }
  | {
      status: 'scored';
      accuracy: number;
      wordBreakdown: WordResult[];
      extraWordsSaid: string[];
      wordsToPractice: number | null;
      wordCounts: { right: number; total: number; notSure: number } | null;
      triesLeft: number;
    };

export type ReadingOutcome =
  | { status: 'unclear'; final: boolean }
  | {
      status: 'scored';
      learner: LearnerPayload;
      accuracy: number;
      wcpm: number | null;
      levelBefore: string;
      levelAfter: string;
      levelChanged: boolean;
      levelWentUp: boolean;
      progress: {
        moved: 'up' | 'down' | null;
        stepChanged: boolean;
        stepAfter: string | null;
        have: number;
        need: number;
        nextStep: string | null;
      } | null;
      pointsEarned: number;
      wordBreakdown: WordResult[];
      extraWordsSaid: string[];
      wordsToPractice: number | null;
      wordCounts: { right: number; total: number; notSure: number } | null;
      comprehension: {
        correctCount: number;
        totalCount: number;
        breakdown: { question: string; picked: string | null; correctAnswer: string; isCorrect: boolean }[];
      } | null;
      newBadges: NewBadge[];
    };

export type CheckOutcome =
  | { status: 'unclear'; final: boolean }
  | { status: 'continue'; message: string; passagesDone: number; maxPassages: number }
  | { status: 'finished'; finalLevel: string; resultLabel: string | null; newBadges: NewBadge[] };

export type ActivityOption = {
  id: number;
  title: string;
  competency: string | null;
  difficultyTier: string | null;
  source: string;
  isRecommended: boolean;
  practice: string | null;
  wordCount: number | null;
  /** Too long for where this child is now. Shown last and marked "for later", never hidden. */
  later: boolean;
};

export type ActivityDetail = {
  id: number;
  title: string;
  instructions: string | null;
  passageText: string;
  wordCount: number | null;
  /** Questions and their choices only. The right answer never leaves the server. */
  quizQuestions: { question: string; choices: string[] }[];
  practice: { triesLeft: number; startAt: 'listen' | 'try' | 'real' };
};

export type DiagnosticPassage = {
  activity: { id: number; title: string; passageText: string; kind: 'letters' | 'passage'; prompt: string; letters: { upper: string; lower: string }[] | null };
  passageNumber: number;
  maxPassages: number;
};

export async function getToken() {
  return storage.getItem(TOKEN_KEY);
}

export async function saveToken(token: string) {
  await storage.setItem(TOKEN_KEY, token);
}

export async function clearToken() {
  await storage.removeItem(TOKEN_KEY);
}

async function request<T>(path: string, init: RequestInit = {}, options: { withToken?: boolean; timeoutMs?: number } = {}): Promise<T> {
  const { withToken = true, timeoutMs = REQUEST_TIMEOUT_MS } = options;
  const headers: Record<string, string> = { Accept: 'application/json', ...(init.headers as Record<string, string>) };
  if (withToken) {
    const token = await getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  let response: Response;
  try {
    response = await fetch(`${API_URL}${path}`, { ...init, headers, signal: controller.signal });
  } catch {
    throw new ApiError('We could not reach TaraBasa. Check the internet and try again.', 0);
  } finally {
    clearTimeout(timer);
  }

  const text = await response.text();
  let body: any = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = null;
  }

  if (!response.ok) {
    if (response.status === 401 && withToken) onUnauthorized?.();
    // Laravel validation errors come back as { message, errors: { field: [..] } }.
    const first = body?.errors ? (Object.values(body.errors)[0] as string[] | undefined)?.[0] : undefined;
    throw new ApiError(first ?? body?.message ?? `Something went wrong (${response.status}).`, response.status, body);
  }
  return body as T;
}

/** Sends one recording (and any quiz answers) to a scoring endpoint as a multipart upload. */
async function uploadAudio<T>(path: string, uri: string, answers?: string[]): Promise<T> {
  const form = new FormData();
  if (Platform.OS === 'web') {
    // A browser needs a real file object; a phone takes the file's address.
    form.append('audio', await (await fetch(uri)).blob(), 'recording.m4a');
  } else {
    form.append('audio', { uri, name: 'recording.m4a', type: 'audio/mp4' } as unknown as Blob);
  }
  (answers ?? []).forEach((choice, i) => form.append(`answers[${i}]`, choice));

  // No Content-Type header on purpose: the phone adds the multipart boundary itself.
  return request<T>(path, { method: 'POST', body: form }, { timeoutMs: UPLOAD_TIMEOUT_MS });
}

export function login(learnerCode: string, pin: string) {
  return request<LoginResult>(
    '/api/learner/login',
    { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ learner_code: learnerCode, pin }) },
    { withToken: false },
  );
}

export function logout() {
  return request<{ ok: boolean }>('/api/learner/logout', { method: 'POST' });
}

export function home() {
  return request<Home>('/api/learner/dashboard');
}

/** Wakes the sleeping scoring service as a reading screen opens, so the first recording is not slow. */
export function warm() {
  return request<null>('/api/learner/warm');
}

export function activities() {
  return request<{ options: ActivityOption[] }>('/api/learner/activities');
}

export function activityDetail(id: number) {
  return request<ActivityDetail>(`/api/learner/activities/${id}`);
}

export function practiceTry(id: number, uri: string) {
  return uploadAudio<PracticeOutcome>(`/api/learner/activities/${id}/practice`, uri);
}

export function realReading(id: number, uri: string, answers?: string[]) {
  return uploadAudio<ReadingOutcome>(`/api/learner/activities/${id}/record`, uri, answers);
}

/** Saves the child's text size choice (1 to 5) so it is the same on the website. */
export function setFontStep(step: number) {
  return request<{ ok: boolean }>('/api/learner/reading-preferences/font-step', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ step }),
  });
}

export function firstCheckPassage() {
  return request<DiagnosticPassage>('/api/learner/diagnostic/passage');
}

export function firstCheckRecord(uri: string) {
  return uploadAudio<CheckOutcome>('/api/learner/diagnostic/record', uri);
}

/**
 * Wakes the sleeping server as the sign in screen opens, so it is ready by the time the code and PIN are typed.
 * The free host sleeps after a quiet while. A plain page request that stays connected is what wakes it. Never throws.
 */
export function wakeServer() {
  fetch(`${API_URL}/up`).catch(() => {});
}
