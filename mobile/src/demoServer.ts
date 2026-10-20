// Demo mode, browser only. Used to show the app in a hosted preview with no server: every request to /api/learner/* is
// answered here with sample data in exactly the shape the real server sends (see api.ts). It is switched on by building the
// web version with EXPO_PUBLIC_DEMO=1. A phone build never sets that, so none of this runs there (and the bundler drops it).
//
// Nothing here is a real child. The names, words and numbers are made up for the preview. A microphone is not used: the
// screens that follow a reading are shown from the gallery list instead (see devGallery.tsx).
import type {
  ActivityDetail,
  ActivityOption,
  Book,
  BookDetail,
  GamesInfo,
  Home,
  LearnerPayload,
  ReadingOutcome,
  SentenceLevels,
  SyllableLevels,
  TrainLevels,
  WordLevels,
  WordResult,
} from './api';
import { startHash } from './config';
import * as storage from './storage';

export const demoLearner: LearnerPayload = {
  id: 1,
  learnerCode: 'TB26-43708',
  firstName: 'Maria',
  gradeLevel: 'Grade 1',
  avatarId: null,
  avatarPhotoUrl: null,
  masteryLevel: 'Developing',
  points: 120,
  streak: 6,
  dayStreak: 3,
  readingFontStep: 4,
  themeColor: 'blue',
  readingStep: { step: 2, name: 'Word Builder' },
};

const badgeIcons = ['star', 'book-open', 'fire', 'clock', 'plant', 'books', 'trophy', 'rocket', 'target', 'crown'];
const badgeNames = ['First Reading Star', 'Reading Check Done', '3 Days In A Row', 'Night Owl', 'Sprout Reader', 'Word Builder Pro'];

export const demoHome: Home = {
  learner: demoLearner,
  readingPath: [
    { step: 1, name: 'Letter Explorer', skill: 'Letters and Sounds', blurb: 'Say the sounds of letters', isCurrent: false },
    { step: 2, name: 'Word Builder', skill: 'Sounding Out Words', blurb: 'Sound out and read words', isCurrent: true },
    { step: 3, name: 'Sentence Reader', skill: 'Reading Sentences', blurb: 'Read sentences with care', isCurrent: false },
    { step: 4, name: 'Story Reader', skill: 'Reading Stories', blurb: 'Read stories and answer questions', isCurrent: false },
  ],
  pathProgress: { have: 1, need: 3, atTop: false },
  competencyProgress: [],
  streak: { days: 3, week: [] },
  weeklyGoal: { done: 3, target: 5, met: false },
  growth: {
    days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((label, i) => ({
      label,
      count: [1, 2, 0, 1, 0, 0, 0][i],
      isToday: i === 3,
      isFuture: i > 3,
    })),
    scale: 2,
  },
  badges: Array.from({ length: 24 }, (_, i) => ({
    code: `b${i}`,
    name: badgeNames[i % 6] + (i >= 6 ? ` ${Math.floor(i / 6) + 1}` : ''),
    description: 'Finish 5 real reading activities.',
    category: i < 12 ? 'Reading' : 'Streaks',
    icon: badgeIcons[i % badgeIcons.length],
    earned: i % 5 === 0 || i === 1,
    earnedAt: '2026-10-08T09:00:00Z',
    comingSoon: false,
    current: 3,
    target: 5,
  })),
};

export const demoWords: WordResult[] = [
  { text: 'cat', status: 'correct', heard: null },
  { text: 'dog', status: 'correct', heard: null },
  { text: 'pig', status: 'sub', heard: 'big' },
  { text: 'hen', status: 'correct', heard: null },
  { text: 'cow', status: 'unsure', heard: null },
  { text: 'bat', status: 'skip', heard: null },
  { text: 'rat', status: 'repeated', heard: null },
  { text: 'fox', status: 'mispronounced', heard: 'fix' },
];

const options: ActivityOption[] = [
  { id: 11, title: 'Sounds at the Farm', competency: 'Phonics and Word Study', difficultyTier: 'Easy', source: 'Picked just for you', isRecommended: true, practice: null, wordCount: 8, later: false },
  { id: 12, title: 'My Pet Cat', competency: 'Fluency', difficultyTier: 'Easy', source: 'Assigned by your Teacher', isRecommended: false, practice: 'Practice word endings', wordCount: 24, later: false },
  { id: 13, title: 'A Day at the Coral Reef', competency: 'Comprehension', difficultyTier: 'Medium', source: 'Assigned by your Teacher', isRecommended: false, practice: null, wordCount: 62, later: false },
  { id: 14, title: 'The Big Rain', competency: 'Fluency', difficultyTier: 'Medium', source: 'Extra Practice', isRecommended: false, practice: null, wordCount: 38, later: false },
  { id: 15, title: 'A Long Walk to the River', competency: 'Fluency', difficultyTier: 'Hard', source: 'Assigned by your Teacher', isRecommended: false, practice: null, wordCount: 96, later: true },
];

const passages: Record<number, string> = {
  11: 'cat dog pig hen cow bat rat fox',
  12: 'My cat is on the mat. She is soft and she is fat. She naps in the sun. I pat her and she purrs.',
  13: 'Mia and Dad went to the coral reef. They saw a tiny green turtle and a school of bright fish. A crab hid under a rock. Mia waved at the turtle, and the turtle swam away.',
  14: 'Rain fell on the roof all day. We sat by the window and watched it run down the glass. When it stopped, a rainbow came out over the hill.',
  15: 'Lola and I walked to the river after lunch. The path was long and the sun was hot, so we stopped under a mango tree. We ate sweet mangoes and watched the water slip past the stones.',
};

function detail(id: number): ActivityDetail {
  const o = options.find((x) => x.id === id) ?? options[0];
  return {
    id: o.id,
    title: o.title,
    instructions: o.id === 11 ? 'Read each word out loud.' : 'Read the story out loud.',
    passageText: passages[o.id] ?? passages[11],
    wordCount: o.wordCount,
    quizQuestions:
      o.id === 13
        ? [
            { question: 'What did Mia see in the reef?', choices: ['A tiny green turtle', 'A big whale', 'A red boat', 'A sleepy cat'] },
            { question: 'Where did the crab hide?', choices: ['Under a rock', 'In a net', 'On the boat'] },
          ]
        : [],
    practice: { triesLeft: 2, startAt: 'listen' },
  };
}

const books: Book[] = [
  { id: 11, title: 'Sounds at the Farm', wordCount: 8, timesRead: 3, lastRead: '2026-10-12T08:00:00Z' },
  { id: 12, title: 'My Pet Cat', wordCount: 24, timesRead: 1, lastRead: '2026-10-10T08:00:00Z' },
  { id: 14, title: 'The Big Rain', wordCount: 38, timesRead: 2, lastRead: '2026-10-08T08:00:00Z' },
];

const gamesInfo: GamesInfo = { recommended: 'word-train', reason: 'first-words', startLevel: 1, syllableSteps: true };

const pool = (struggling: string[], fallback: string[]) => ({ struggling, favoured: [], fallback });
const wordLevels: WordLevels = {
  startLevel: 1,
  levels: {
    '1': pool(['hen'], ['cat', 'dog', 'sun', 'hat', 'pig', 'bed', 'cup', 'run', 'big']),
    '2': pool([], ['frog', 'jump', 'milk', 'tree', 'star', 'fish', 'nest', 'sand']),
    '3': pool([], ['planet', 'window', 'garden', 'rabbit', 'yellow', 'basket', 'pencil', 'cookie']),
  },
};
const trainLevels: TrainLevels = { startLevel: 1, mode: 'words', cars: { '1': 3, '2': 4, '3': 5 }, levels: wordLevels.levels };
const sentenceLevels: SentenceLevels = {
  startLevel: 1,
  levels: {
    '1': { preferred: [], fallback: ['The cat sat.', 'A dog can run.', 'I see a pig.', 'The sun is hot.'] },
    '2': { preferred: [], fallback: ['The frog sat on a log.', 'We swim in the pond.', 'A fish can swim fast.'] },
    '3': {
      preferred: [],
      fallback: ['The farmer feeds the chickens and the pigs.', 'We plant seeds in the garden and water them every day.', 'The rain stopped, and a bright rainbow came out.'],
    },
  },
};

const sw = (word: string, parts: string[], sentence: string) => ({ word, parts, sentence });
const syllableLevels: SyllableLevels = {
  startLevel: 1,
  repeat: 4,
  levels: {
    '1': {
      own: [],
      level: [sw('sunset', ['sun', 'set'], 'We watch the sunset.'), sw('hotdog', ['hot', 'dog'], 'Dad cooks a hotdog.'), sw('basket', ['bas', 'ket'], 'The basket is big.'), sw('picnic', ['pic', 'nic'], 'We eat at a picnic.')],
      review: [],
    },
    '2': {
      own: [],
      level: [sw('cupcake', ['cup', 'cake'], 'I like a cupcake.'), sw('pancake', ['pan', 'cake'], 'Mom makes a pancake.'), sw('bedtime', ['bed', 'time'], 'It is bedtime now.')],
      review: [sw('sunset', ['sun', 'set'], 'We watch the sunset.')],
    },
    '3': {
      own: [],
      level: [sw('lemon', ['lem', 'on'], 'The lemon is sour.'), sw('cabin', ['cab', 'in'], 'We sleep in a cabin.'), sw('robin', ['rob', 'in'], 'A robin sat on the fence.')],
      review: [sw('cupcake', ['cup', 'cake'], 'I like a cupcake.')],
    },
  },
};

const scored = (): Extract<ReadingOutcome, { status: 'scored' }> => ({
  status: 'scored',
  learner: demoLearner,
  accuracy: 75,
  wcpm: 40,
  levelBefore: 'Developing',
  levelAfter: 'Developing',
  levelChanged: false,
  levelWentUp: false,
  progress: { moved: null, stepChanged: false, stepAfter: null, have: 2, need: 3, nextStep: 'Sentence Reader' },
  pointsEarned: 33,
  wordBreakdown: demoWords,
  extraWordsSaid: [],
  wordsToPractice: 4,
  wordCounts: { right: 4, total: 8, notSure: 1 },
  comprehension: null,
  newBadges: [],
});

function json(body: unknown, status = 200): Response {
  if (body === null) return new Response(null, { status });
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

async function answer(path: string, init?: RequestInit): Promise<Response> {
  const method = (init?.method ?? 'GET').toUpperCase();
  const form = init?.body instanceof FormData ? init.body : null;

  if (path === '/api/learner/login') return json({ token: 'demo', needsDiagnostic: false, learner: demoLearner });
  if (path === '/api/learner/logout') return json({ ok: true });
  if (path === '/api/learner/dashboard') return json(demoHome);
  if (path === '/api/learner/warm') return json(null, 204);
  if (path === '/api/learner/activities') return json({ options });
  const act = /^\/api\/learner\/activities\/(\d+)(?:\/(practice|record))?$/.exec(path);
  if (act) {
    const id = Number(act[1]);
    if (act[2] === 'practice') return json({ status: 'scored', accuracy: 75, wordBreakdown: demoWords, extraWordsSaid: [], wordsToPractice: 4, wordCounts: { right: 4, total: 8, notSure: 1 }, triesLeft: 1 });
    if (act[2] === 'record') return json(scored());
    return json(detail(id));
  }
  if (path === '/api/learner/bookshelf') return json({ books });
  const shelf = /^\/api\/learner\/bookshelf\/(\d+)(?:\/(reread))?$/.exec(path);
  if (shelf) {
    const id = Number(shelf[1]);
    if (shelf[2] === 'reread') return json({ status: 'scored', accuracy: 88, wordBreakdown: demoWords, extraWordsSaid: [], wordsToPractice: 2, wordCounts: { right: 7, total: 8, notSure: 0 } });
    const d = detail(id);
    const b: BookDetail = { id: d.id, title: d.title, instructions: d.instructions, passageText: d.passageText, wordCount: d.wordCount };
    return json(b);
  }
  if (path === '/api/learner/games') return json(gamesInfo);
  if (path === '/api/learner/games/words') return json(wordLevels);
  if (path === '/api/learner/games/train') return json(trainLevels);
  if (path === '/api/learner/games/sentences') return json(sentenceLevels);
  if (path === '/api/learner/games/syllables') return json(syllableLevels);
  if (/^\/api\/learner\/games\/[a-z-]+\/finish$/.test(path) && method === 'POST') return json({ newBadges: [] });
  if (path === '/api/learner/games/check-word') return json({ status: 'heard' });
  if (path === '/api/learner/games/check-text') {
    const text = String(form?.get('text') ?? '');
    return json({ status: 'heard', matched: text.split(/\s+/).filter(Boolean).map(() => true) });
  }
  if (path === '/api/learner/reading-preferences/font-step') return json({ ok: true });
  return json({ message: 'Not part of the preview.' }, 404);
}

/** Replaces the browser's fetch so the app talks to the sample data above. Safe to call more than once. */
export function installDemoServer() {
  const g = globalThis as typeof globalThis & { __tbDemo?: boolean };
  if (g.__tbDemo || typeof g.fetch !== 'function') return;
  g.__tbDemo = true;
  const real = g.fetch.bind(g);
  g.fetch = (async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url;
    const m = /^https?:\/\/[^/]+(\/api\/learner\/[^?#]*)/.exec(url);
    if (m) {
      await new Promise((r) => setTimeout(r, 180)); // a short pause so the "getting it" states are seen, like a real answer
      return answer(m[1], init);
    }
    if (/\/up(\?|$)/.test(url)) return json(null, 204);
    return real(input as RequestInfo, init);
  }) as typeof fetch;
  // A child who is "already signed in", so the preview opens on Home. #demo=signin shows the sign in screen instead.
  // (storage keeps it in memory when the browser refuses local storage, as a sandboxed frame does)
  if (/#demo=signin/.test(startHash())) void storage.removeItem('tarabasa_token');
  else void storage.setItem('tarabasa_token', 'demo');
  void storage.setItem('tarabasa_first_name', 'Maria');
}
