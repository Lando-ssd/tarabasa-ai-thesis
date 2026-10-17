import { Platform } from 'react-native';
import { API_URL, REQUEST_TIMEOUT_MS, UPLOAD_TIMEOUT_MS } from './config';
import { discardRecording } from './recordingFiles';
import * as storage from './storage';

const TOKEN_KEY = 'tarabasa_token';
const ACTOR_KEY = 'tarabasa_actor';

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
  /** Great readings counted toward growing on the child's own step (for the ring on the reading path). */
  pathProgress?: { have: number; need: number; atTop: boolean };
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

/** A story on the shelf: one the child really read. Times read and the last day, never a percentage. */
export type Book = { id: number; title: string; wordCount: number | null; timesRead: number; lastRead: string | null };

export type BookDetail = { id: number; title: string; instructions: string | null; passageText: string; wordCount: number | null };

/** A free re-read: feedback only. Nothing is saved, so there are no points and no level here. */
export type RereadOutcome =
  | { status: 'unclear' }
  | {
      status: 'scored';
      accuracy: number;
      wordBreakdown: WordResult[];
      extraWordsSaid: string[];
      wordsToPractice: number | null;
      wordCounts: { right: number; total: number; notSure: number } | null;
    };

/** What the Games screen needs from the server. */
export type GamesInfo = {
  /** A game the child's own reading says would help most, or null. Never a gate: every game is always playable. */
  recommended: GameName | null;
  /** Why: 'letters', 'own-words', 'first-words' or 'sentences'. */
  reason: 'letters' | 'own-words' | 'first-words' | 'sentences' | null;
  /** The level a game starts at, 1 to 3, from the child's step on the reading path. */
  startLevel: number;
};

/** The words a game can use at each level (the child's own missed words first, then ones that practise their kind of mistake, then the level's list). */
export type WordPool = { struggling: string[]; fallback: string[]; favoured: string[] };
export type WordLevels = {
  startLevel: number;
  levels: Record<string, WordPool>;
};

/** Word Train: rows of words, or rows of letters for a child still on the letters step. */
export type TrainLevels = {
  startLevel: number;
  mode: 'letters' | 'words';
  /** How many cars at each level. */
  cars: Record<string, number>;
  levels: Record<string, WordPool>;
};

/** Sentence Stars: the level's sentences, and the ones that suit this child listed first as "preferred". */
export type SentenceLevels = {
  startLevel: number;
  levels: Record<string, { preferred: string[]; fallback: string[] }>;
};

/** Every game is a read-aloud game. */
export type GameName = 'balloon-pop' | 'word-train' | 'sentence-stars';

/** What a finished game tells the server, so the Games badges can be earned. */
export type GameFinishBody = { level_reached: number; top_level_cleared: boolean; had_perfect_round: boolean; rounds: number };

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
  await storage.removeItem(ACTOR_KEY);
}

/** Who the saved token belongs to, so the app opens the right screens when it starts again. */
export type Actor = 'learner' | 'teacher' | 'parent';

export async function getActor(): Promise<Actor> {
  const saved = await storage.getItem(ACTOR_KEY);
  // A phone that signed in before Teachers and Parents existed only ever held a child's token.
  return saved === 'teacher' || saved === 'parent' ? saved : 'learner';
}

export async function saveActor(actor: Actor) {
  await storage.setItem(ACTOR_KEY, actor);
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
async function uploadAudio<T>(path: string, uri: string, answers?: string[], fields?: Record<string, string>): Promise<T> {
  const form = new FormData();
  if (Platform.OS === 'web') {
    // A browser needs a real file object; a phone takes the file's address.
    form.append('audio', await (await fetch(uri)).blob(), 'recording.m4a');
  } else {
    form.append('audio', { uri, name: 'recording.m4a', type: 'audio/mp4' } as unknown as Blob);
  }
  (answers ?? []).forEach((choice, i) => form.append(`answers[${i}]`, choice));
  Object.entries(fields ?? {}).forEach(([name, value]) => form.append(name, value));

  // No Content-Type header on purpose: the phone adds the multipart boundary itself.
  const result = await request<T>(path, { method: 'POST', body: form }, { timeoutMs: UPLOAD_TIMEOUT_MS });
  // The server answered, so the copy of the child's voice on the phone is not needed any more. If the send FAILED the
  // file is kept on purpose, so "Send Again" works; the cache sweep removes it later.
  discardRecording(uri);
  return result;
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

// ------------------------------------------------------------------ Teacher and Parent accounts (email and password)

/** What the app may know about a Teacher or Parent. The server never sends a password, a token list or anything internal. */
export type Account = {
  id: number;
  role: 'teacher' | 'parent';
  firstName: string;
  middleInitial: string | null;
  lastName: string;
  email: string;
  contactNumber: string | null;
  /** Only for a Teacher. status is Pending until the Admin approves the school details. */
  teacher?: { status: 'Pending' | 'Active' | 'Rejected' | string | null; schoolName: string | null; grades: string[]; freeCredits: number };
};

export type AccountLogin = { token: string; account: Account };

/** The server's answer to a sign up. With "confirm your email first" on (the default) there is no token yet. */
export type Registered =
  | { status: 'confirm_email'; email: string; maskedEmail: string; firstName: string; role: 'teacher' | 'parent' }
  | { status: 'signed_in'; token: string; account: Account };

export type TeacherSignUp = {
  first_name: string;
  middle_initial: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  school_name: string;
  employee_id: string;
  contact_number?: string;
  grades_mode: 'single' | 'multi';
  grades_handled: string[];
};

export type ParentSignUp = Omit<TeacherSignUp, 'school_name' | 'employee_id' | 'contact_number' | 'grades_mode' | 'grades_handled'>;

const json = (body: unknown): RequestInit => ({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });

/** Email and password. Refusals carry the website's own wording, and `body.error` says why (see accountRefusal). */
export function accountLogin(email: string, password: string) {
  return request<AccountLogin>('/api/auth/login', json({ email: email.trim().toLowerCase(), password }), { withToken: false });
}

export function registerTeacher(body: TeacherSignUp) {
  return request<Registered>('/api/auth/register/teacher', json(body), { withToken: false });
}

export function registerParent(body: ParentSignUp) {
  return request<Registered>('/api/auth/register/parent', json(body), { withToken: false });
}

/** "Send the link again". The answer is the same whether or not the address has an account. */
export function resendVerification(email: string) {
  return request<{ message: string }>('/api/auth/resend-verification', json({ email: email.trim().toLowerCase() }), { withToken: false });
}

export function forgotPassword(email: string) {
  return request<{ message: string }>('/api/auth/forgot-password', json({ email: email.trim().toLowerCase() }), { withToken: false });
}

/** Who this saved sign in belongs to. A 401 means it is no longer good. */
export function accountMe() {
  return request<{ account: Account }>('/api/auth/me');
}

export function accountLogout() {
  return request<{ ok: boolean }>('/api/auth/logout', { method: 'POST' });
}

// ------------------------------------------------------------------ Teacher screens

export type TeacherHome = {
  schoolYear: string;
  counts: { classes: number; learners: number; activities: number; approved: number; drafts: number; alerts: number; claim: number; credits: number };
  needsSupport: { name: string; title: string; why: string }[];
  needsSupportCount: number;
};

/** The short tag a learner carries when they need the teacher: "Needs support", "Starting to read", "Moved up", "Ready to move up", "Quiet 9 days". */
export type RosterTag = { key: 'support' | 'start' | 'moved' | 'up' | 'quiet'; label: string; tone: 'red' | 'blue' | 'green' | 'amber' };

export type RosterLearner = {
  id: number;
  firstName: string;
  lastName: string;
  name: string;
  code: string;
  grade: string | null;
  /** The Phil-IRI level: Non-reader, Frustration, Instructional, Independent, or "Not checked yet". */
  level: string;
  /** Where the child reads on the ladder (Letters, Words, Short sentences...), once the first check is done. */
  step: string | null;
  tag: RosterTag | null;
  /** A quiet note when the child reads far above or below most of the class. */
  levelCheck: { direction: 'above' | 'below'; text: string } | null;
};

export type ClassCard = {
  id: number;
  name: string;
  grade: string;
  section: string;
  groupTag: string | null;
  schoolYear: string;
  readOnly: boolean;
  learnerCount: number;
  activityCount: number;
  groups: { key: 'support' | 'instructional' | 'independent'; title: string; sub: string; count: number }[];
};

export type ClassList = { schoolYear: string; currentSchoolYear: string; /** Every school year this teacher has classes in, newest first. */ years?: string[]; readOnly: boolean; grades: string[]; classes: ClassCard[] };

export type ClassDetail = {
  class: ClassCard;
  canAdd: boolean;
  generalLevel: { label: string; source: 'learners' | 'grade' };
  learners: RosterLearner[];
  activities: { id: number; title: string; level: string | null; via: 'class' | 'band' | 'group'; band: string | null; tag: string | null }[];
};

export type ClassBody = { name: string; grade_level: string; section: string; group_tag?: string };

/** What the parent shared, where the child reads now, and what suits them: the website's learner window, as data. */
/** Another class of the same grade a child can be moved to. */
export type MoveTarget = { id: number; name: string; grade: string; section: string };

export type LearnerPage = {
  learner: RosterLearner;
  /** Moving needs an Active teacher and a school year in progress. */
  canMove?: boolean;
  moveTargets?: MoveTarget[];
  profile: {
    stage: string | null;
    language: string | null;
    supports: string[];
    interests: string[];
    answers: { question: string; answer: string }[];
    check: { done: boolean; step: string | null; band: string | null; level: string | null };
    startsAt: string | null;
    mismatch: string | null;
    bestFit: { step: string; skill: string; code: string; words: string; level: string; advice: string };
    teach: { element: string; focus: string; moves: string[]; ready: string; watch: string; basis: string };
    pattern: { says: string; tip: string; code: string; codeText: string; early: boolean; examples: string[]; readings: number } | null;
    progress: { rung: number; step: string; rungLabel: string; nextRungLabel: string | null; have: number; need: number; atTop: boolean } | null;
  };
};

export type JoinResult = { ok: true; learner: RosterLearner } | { ok: false; message: string };

export const teacherHome = () => request<TeacherHome>('/api/teacher/home');

export const teacherClasses = (year?: string) => request<ClassList>(`/api/teacher/classes${year ? `?school_year=${encodeURIComponent(year)}` : ''}`);

export const teacherClass = (id: number) => request<ClassDetail>(`/api/teacher/classes/${id}`);

export const teacherLearner = (classId: number, learnerId: number) => request<LearnerPage>(`/api/teacher/classes/${classId}/learners/${learnerId}`);

export const createClass = (body: ClassBody & { school_year: string }) => request<{ class: ClassCard }>('/api/teacher/classes', json(body));

export const moveLearner = (classId: number, learnerId: number, toClassId: number) =>
  request<{ message: string; class: { id: number; name: string } }>(`/api/teacher/classes/${classId}/learners/${learnerId}/move`, json({ to_class_id: toClassId }));

export const updateClass = (id: number, body: ClassBody) =>
  request<{ class: ClassCard }>(`/api/teacher/classes/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });

/**
 * Put a learner in a class from a code typed (the last 5 characters) or scanned. A refusal is an answer, not an error: it
 * comes back as { ok: false, message } in the website's own words, so the scanner can show it and keep going.
 */
export async function joinLearner(classId: number, code: string): Promise<JoinResult> {
  try {
    return { ok: true, learner: (await request<{ ok: true; learner: RosterLearner }>(`/api/teacher/classes/${classId}/learners`, json({ learner_code: code }))).learner };
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && typeof (e.body as { message?: unknown } | null)?.message === 'string') {
      return { ok: false, message: e.message };
    }
    throw e;
  }
}

// ------------------------------------------------------------------ Teacher: Activities, Alerts, More

export type Level = 'Easy' | 'Medium' | 'Hard';
export type ActivityStatus = 'Draft' | 'Approved' | 'Rejected';

/** The request being written in the background, as the Activities screen follows it. */
export type GenerationStatus = {
  id: number;
  status: 'Queued' | 'Running' | 'Done' | 'Failed' | string;
  active: boolean;
  message: string | null;
  total: number;
  summary: string;
  startedAt: number | null;
};

export type ActivityRow = {
  id: number;
  title: string;
  grade: string;
  type: string;
  level: Level | null;
  aiLevel: Level | null;
  moved: boolean;
  words: number | null;
  status: ActivityStatus;
  shared: boolean;
  assigned: number;
  why: string | null;
  passage: string | null;
};

export type ActivityList = {
  status: ActivityStatus;
  counts: Record<ActivityStatus, number>;
  tiers: Level[];
  locked: boolean;
  credits: number;
  generation: GenerationStatus | null;
  activities: ActivityRow[];
};

/** `reason` is 'grade' when it is blocked because it is another grade (a class only gets activities of its own grade), otherwise it is about length. */
export type Fit = { verdict: 'ok' | 'caution' | 'blocked' | 'unknown'; note: string | null; reason?: string | null };

export type ActivityPage = ActivityRow & {
  instructions: string;
  competency: string | null;
  topic: string | null;
  features: string[];
  levelMeaning: string | null;
  band: { min: number; max: number } | null;
  lengthFits: boolean | null;
  basis: { className: string; lines: string[] } | null;
  givenTo: string[];
  listing: { price: string; stars: number | null; ratings: number } | null;
  /** Only for an Approved activity: where it can be given, and how well it suits each place. */
  targets?: {
    classes: { id: number; name: string; grade: string; fit: Fit }[];
    groups: { tag: string; fit: Fit }[];
    learners: { id: number; name: string; className: string | null; fit: Fit }[];
  };
};

export type PlaceResult = { message: string; undo: boolean; activity: ActivityRow };

export type GenerateOptions = {
  credits: number;
  locked: boolean;
  grades: string[];
  competencies: { key: string; label: string; description: string; types: string[] }[];
  typeLabels: Record<string, string>;
  /** [min, max] words for each activity type, grade number and level (the keys easy, medium, hard). */
  bands: Record<string, Record<string, Record<string, [number, number]>>>;
  levelInfo: Record<Level, { short: string; long: string }>;
  maxPerLevel: number;
  /** For each grade number, activity type and level: [approved, drafts]. */
  have: Record<string, Record<string, Record<string, [number, number]>>>;
  baseSeconds: number;
  extraSeconds: number;
  classes: { id: number; name: string; grade: string }[];
  generation: GenerationStatus | null;
};

export type GenerateBody = {
  grade_level: string;
  competency: string;
  activity_type: string;
  levels: Record<Level, number>;
  topic?: string;
  teacher_notes?: string;
  class_id?: number;
};

export type AlertKind = 'support' | 'start' | 'up' | 'quiet';

export type AlertItem = {
  kind: AlertKind;
  variant: string | null;
  learnerId: number;
  classId: number | null;
  className: string | null;
  name: string;
  title: string;
  evidence: string;
  why: string | null;
  pattern: string | null;
  basis: string | null;
  at: string;
  suggestion: {
    level: Level | null;
    tip: string | null;
    activity: { id: number; title: string; words: number | null; level: Level | null; skill: string | null; code: string | null; why: string | null } | null;
  } | null;
};

export type AlertList = { filter: 'all' | AlertKind; counts: Record<'all' | AlertKind, number>; alerts: AlertItem[] };

export type AnalyticsOverview = {
  period: 'week' | 'month' | 'all';
  periods: Record<string, string>;
  classes: { id: number; name: string; grade: string; schoolYear: string }[];
  scope: string;
  learners: number;
  readings: number;
  bySource: { teacher: number; parent: number };
  average: number | null;
  change: number | null;
  needSupport: number;
  levels: { key: string; label: string; count: number }[];
  checked: number;
  missedWords: Record<string, number>;
  patterns: { key: string; label: string; misses: number; learners: number }[];
  assignments: { title: string; for: string; total: number; done: number; average: number | null }[];
};

export type PromotionsData = {
  release: { id: number; name: string; grade: string; learners: { id: number; name: string; nextGrade: string | null }[] }[];
  claim: { id: number; name: string; nextGrade: string; releasedBy: string | null; releasedAt: string | null; classes: { id: number; name: string }[]; note?: string | null }[];
  claimable: number;
};

// ------------------------------------------------------------------ Parent

export type ParentLevel = { band: string; label: string; short: string; step: string | null };

/** One of the parent's children, as every Parent screen lists them. */
export type ParentChild = {
  id: number;
  firstName: string;
  name: string;
  grade: string | null;
  className: string | null;
  code: string;
  /** The character the parent chose (an animal picture). */
  avatar: string | null;
  /** The child's latest reading was flagged for extra support. */
  flagged: boolean;
  level: ParentLevel;
  /** How many other guardians the child has. Removing a child who has one only removes this parent. Missing means none (a child just added). */
  otherGuardians?: number;
};

export type ParentSession = {
  id: number;
  title: string;
  source: 'Teacher' | 'Parent' | string;
  accuracy: number | null;
  wcpm: number | null;
  flagged: boolean;
  /** The level the child moved to with this reading, when it changed. */
  moved: string | null;
  day: string;
  at: string;
};

export type ChildWeek = {
  dayStreak: number;
  weeklyCount: number;
  weeklyTarget: number;
  weeklyLeft: number;
  weeklyMet: boolean;
  badgesEarned: number;
  badgesTotal: number;
  latestBadges: { name: string; icon: string }[];
};

export type SourceSplit = { teacher: { count: number; average: number | null }; parent: { count: number; average: number | null } };

export type ParentHome = {
  children: ParentChild[];
  selected: number | null;
  unread: number;
  child:
    | (ParentChild & {
        points: number;
        wcpm: number | null;
        summary: ChildWeek;
        sources: SourceSplit;
        recent: ParentSession[];
      })
    | null;
};

export type ParentChildRow = ParentChild & { dayStreak: number; relationship: string | null; creator: boolean };

export type ParentProgress = {
  children: ParentChild[];
  selected: number | null;
  child:
    | (ParentChild & {
        level: ParentLevel & { plain: string };
        home: { say: string; tip: string; code: string; codeText: string; early: boolean; examples: string[]; words: string[] } | null;
        summary: ChildWeek;
        sources: SourceSplit;
        readings: number;
        trend: (number | null)[];
        history: ParentSession[];
      })
    | null;
};

export type ParentListing = {
  id: number;
  title: string;
  grade: string;
  skill: string;
  level: Level | null;
  words: number | null;
  teacher: string | null;
  paid: boolean;
  price: string;
  unlocked: boolean;
  stars: number | null;
  ratings: number;
  mine: { rating: number; comment: string | null } | null;
  matchesLevel: boolean;
  matchesPattern: string | null;
};

export type ParentRepository = {
  children: ParentChild[];
  selected: number | null;
  filter: 'all' | 'free' | 'paid' | 'unlocked';
  best: boolean;
  hasMatching: boolean;
  listings: ParentListing[];
};

export type ParentAlert = {
  id: number;
  type: string;
  attention: boolean;
  message: string;
  read: boolean;
  childId: number | null;
  child: string | null;
  day: string;
  at: string;
};

export type ParentAlerts = { filter: 'all' | 'attention' | 'routine'; unread: number; alerts: ParentAlert[] };

export type ChildOptions = {
  grades: string[];
  avatars: string[];
  homeLanguages: string[];
  readingStages: { key: string; label: string }[];
  supports: { key: string; label: string }[];
  interests: { key: string; label: string }[];
  placementQuestions: Record<string, string[]>;
};

export type NewChild = {
  first_name: string;
  middle_name?: string;
  last_name: string;
  grade_level: string;
  avatar_id: string;
  reading_stage: string;
  home_language?: string;
  supports: string[];
  interests: string[];
  q1: string;
  q2: string;
  q3: string;
  pin: string;
  pin_confirmation: string;
};

const put = (body: unknown): RequestInit => ({ method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });

const withChild = (path: string, learnerId: number | null, more: Record<string, string> = {}) => {
  const params = new URLSearchParams(more);
  if (learnerId !== null) params.set('learner_id', String(learnerId));
  const qs = params.toString();
  return `${path}${qs ? `?${qs}` : ''}`;
};

export const parentHome = (learnerId: number | null) => request<ParentHome>(withChild('/api/parent/home', learnerId));

export const parentChildren = () => request<{ children: ParentChildRow[] }>('/api/parent/children');

export const parentChildOptions = () => request<ChildOptions>('/api/parent/children/options');

export const addChild = (body: NewChild) => request<{ child: ParentChild; message: string }>('/api/parent/children', json(body));

export const linkChild = (body: { learner_code: string; pin: string; relationship: string }) =>
  request<{ child: ParentChild; message: string }>('/api/parent/children/link', json(body));

export const childCard = (id: number) => request<{ name: string; firstName: string; code: string; qrSvg: string }>(`/api/parent/children/${id}/card`);

/**
 * A guardian makes a new PIN for a child who forgot theirs. `generate` returns the PIN once in `pin` (null for `choose`, which the guardian
 * typed). A refusal is a 422 under `password` or `pin` in the website's words: a wrong password, three changes already made today, an easy PIN.
 */
export const resetChildPin = (id: number, body: { mode: 'generate' | 'choose'; password: string; pin?: string; pin_confirmation?: string }) =>
  request<{ pin: string | null; message: string }>(`/api/parent/children/${id}/pin`, json(body));

/**
 * A guardian removes a child. Alone as a guardian: the profile and every record are deleted for good (`erased` true). With another
 * guardian linked: only this parent is removed. A refusal is a 422 under `password` in the website's words.
 */
export const removeChild = (id: number, password: string) => request<{ erased: boolean; message: string }>(`/api/parent/children/${id}/remove`, json({ password }));

export const parentProgress = (learnerId: number | null) => request<ParentProgress>(withChild('/api/parent/progress', learnerId));

export const parentRepository = (learnerId: number | null, filter: 'all' | 'free' | 'paid' | 'unlocked', best: boolean) =>
  request<ParentRepository>(withChild('/api/parent/repository', learnerId, { filter, ...(best ? { best: '1' } : {}) }));

export const unlockListing = (listingId: number, learnerId: number) => request<{ message: string }>(`/api/parent/repository/${listingId}/unlock`, json({ learner_id: learnerId }));

export const rateListing = (listingId: number, learnerId: number, rating: number) =>
  request<{ message: string }>(`/api/parent/repository/${listingId}/rate`, json({ learner_id: learnerId, rating }));

export const parentAlerts = (filter: 'all' | 'attention' | 'routine') => request<ParentAlerts>(`/api/parent/alerts${filter === 'all' ? '' : `?filter=${filter}`}`);

export const markParentAlertRead = (id: number) => request<{ unread: number }>(`/api/parent/alerts/${id}/read`, json({}));

export const markAllParentAlertsRead = () => request<{ unread: number }>('/api/parent/alerts/read-all', json({}));

export const teacherActivities = (query: { status?: ActivityStatus; tier?: Level | null; q?: string } = {}) => {
  const params = new URLSearchParams();
  if (query.status) params.set('status', query.status);
  if (query.tier) params.set('tier', query.tier);
  if (query.q?.trim()) params.set('q', query.q.trim());
  const qs = params.toString();
  return request<ActivityList>(`/api/teacher/activities${qs ? `?${qs}` : ''}`);
};

export const teacherActivity = (id: number) => request<ActivityPage>(`/api/teacher/activities/${id}`);

export const placeActivity = (id: number, level: Level | 'reject') => request<PlaceResult>(`/api/teacher/activities/${id}/place`, json({ level }));

export const restoreActivity = (id: number) => request<{ message: string; activity: ActivityRow }>(`/api/teacher/activities/${id}/restore`, json({}));

export const editActivity = (id: number, body: { title: string; instructions: string; passage_text: string; level?: Level | null }) =>
  request<{ message: string; activity: ActivityRow }>(`/api/teacher/activities/${id}`, put(body));

export const assignActivity = (id: number, target: { learner_id?: number; class_id?: number; group_tag?: string }) =>
  request<{ message: string }>(`/api/teacher/activities/${id}/assign`, json(target));

export const shareActivity = (id: number, body: { price_type: 'Free' | 'Paid'; price?: number }) =>
  request<{ message: string; credits: number }>(`/api/teacher/activities/${id}/share`, json(body));

export const generateOptions = () => request<GenerateOptions>('/api/teacher/activities/generate-options');

export const generateBasis = (classId: number, grade: string, activityType: string) =>
  request<{ ok: boolean; enough: boolean; lines: string[] }>(
    `/api/teacher/activities/basis?class_id=${classId}&grade_level=${encodeURIComponent(grade)}&activity_type=${encodeURIComponent(activityType)}`,
  );

export const generateActivities = (body: GenerateBody) => request<{ generation: GenerationStatus }>('/api/teacher/activities/generate', json(body));

export const generationStatus = (id: number) => request<GenerationStatus>(`/api/teacher/activities/generations/${id}`);

export const dismissGeneration = (id: number) => request<{ ok: boolean }>(`/api/teacher/activities/generations/${id}/dismiss`, json({}));

/** Wakes the sleeping activity writer as the Generate screen opens. Never throws. */
export const warmGenerator = () => request<null>('/api/teacher/activities/warm').catch(() => null);

export const assignToClass = (classId: number, activityId: number, readingBand?: string | null) =>
  request<{ message: string }>(`/api/teacher/classes/${classId}/assign`, json({ activity_id: activityId, reading_band: readingBand ?? null }));

export const teacherAlerts = (filter: 'all' | AlertKind = 'all') => request<AlertList>(`/api/teacher/alerts${filter === 'all' ? '' : `?filter=${filter}`}`);

export const markAlertHandled = (learnerId: number, kind: AlertKind) => request<{ message: string }>(`/api/teacher/alerts/${learnerId}/handled`, json({ kind }));

export const assignSuggested = (learnerId: number, kind: AlertKind, activityId: number) =>
  request<{ message: string }>(`/api/teacher/alerts/${learnerId}/assign`, json({ kind, activity_id: activityId }));

export const teacherAnalytics = (period: 'week' | 'month' | 'all', classId: number | null) =>
  request<AnalyticsOverview>(`/api/teacher/analytics?period=${period}&class=${classId ?? 'all'}`);

export const teacherPromotions = () => request<PromotionsData>('/api/teacher/promotions');

export const releaseLearner = (learnerId: number) => request<{ message: string }>(`/api/teacher/promotions/${learnerId}/release`, json({}));

export const claimLearner = (recordId: number, classId: number) => request<{ message: string }>(`/api/teacher/promotions/${recordId}/claim`, json({ class_id: classId }));

export const updateProfile = (body: { first_name: string; middle_initial?: string; last_name: string; contact_number?: string }) =>
  request<{ message: string; account: Account }>('/api/auth/profile', put(body));

export const changePassword = (body: { current_password: string; password: string; password_confirmation: string }) =>
  request<{ message: string }>('/api/auth/profile/password', put(body));

/** What deleting the signed in account would do, in plain sentences, and why it cannot be done yet (`blocked`) when that is so. */
export const eraseInfo = () => request<{ lines: string[]; blocked: string | null }>('/api/auth/account/erase-info');

/** Deletes the signed in Teacher's or Parent's own account (password again). Every sign in goes with it. */
export const deleteAccount = (password: string) => request<{ message: string }>('/api/auth/profile/delete', json({ password }));

export const updateGrades = (body: { grades_mode: 'single' | 'multi'; grades_handled: string[] }) => request<{ message: string; account: Account }>('/api/auth/profile/grades', put(body));

/** Why a sign in was refused (invalid_credentials, too_many_attempts, email_not_confirmed, admin_web_only...) and, for an unconfirmed email, which address. */
export function accountRefusal(e: unknown): { reason: string | null; email: string | null } {
  if (!(e instanceof ApiError)) return { reason: null, email: null };
  const body = e.body as { error?: string; email?: string | null } | null;
  return { reason: body?.error ?? null, email: body?.email ?? null };
}

/** Field by field messages from a failed sign up, keyed by the server's field names (first_name, email, password...). */
export function fieldErrors(e: unknown): Record<string, string> {
  if (!(e instanceof ApiError)) return {};
  const errors = (e.body as { errors?: Record<string, string[]> } | null)?.errors ?? {};
  return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]]));
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

export function bookshelf() {
  return request<{ books: Book[] }>('/api/learner/bookshelf');
}

export function bookDetail(id: number) {
  return request<BookDetail>(`/api/learner/bookshelf/${id}`);
}

export function rereadBook(id: number, uri: string) {
  return uploadAudio<RereadOutcome>(`/api/learner/bookshelf/${id}/reread`, uri);
}

export function games() {
  return request<GamesInfo>('/api/learner/games');
}

export function gameWords() {
  return request<WordLevels>('/api/learner/games/words');
}

export function gameTrain() {
  return request<TrainLevels>('/api/learner/games/train');
}

export function gameSentences() {
  return request<SentenceLevels>('/api/learner/games/sentences');
}

export function finishGame(game: GameName, body: GameFinishBody) {
  return request<{ newBadges: { code: string; name: string; icon: string }[] }>(`/api/learner/games/${game}/finish`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

/** Balloon Pop: one spoken word. The answer is only ever heard, again or unavailable, never "wrong". */
export function checkWord(word: string, uri: string) {
  return uploadAudio<{ status: 'heard' | 'again' | 'unavailable' }>('/api/learner/games/check-word', uri, undefined, { word });
}

/** What the child read aloud in a game: a row of words or letters, or a sentence. */
export type ReadKind = 'row' | 'letters' | 'sentence';

/**
 * Something read aloud in a game. The answer is only ever heard, again or unavailable (never "wrong"), plus, for each word asked,
 * whether it was heard, so the game can show what went well and ask for the rest again. Nothing is kept.
 */
export function checkText(text: string, kind: ReadKind, uri: string) {
  return uploadAudio<{ status: 'heard' | 'again' | 'unavailable'; matched: boolean[] }>('/api/learner/games/check-text', uri, undefined, { text, kind });
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
  // The answer is never read, so the browser preview does not need permission to see it (no-cors); a phone ignores this setting.
  fetch(`${API_URL}/up`, { mode: 'no-cors' }).catch(() => {});
}
