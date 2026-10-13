// Developer only. In the browser preview, open  http://localhost:8081/#gallery=<name>  to see one screen with sample
// data at any phone size, without a server, a microphone or a child. It is never reached in a phone build (App.tsx
// guards it with __DEV__ and the web platform). Names: home, badges, results, results-low, check-intro, check-letters,
// check-next, check-unclear, check-done, warmup, warmup-heard, warmup-quiet, practice, quiz, waiting, failed, crash, path-1 to path-4, soon.
import { useMemo } from 'react';
import { View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import type { Home as HomeData, LearnerPayload, ReadingOutcome, WordResult } from './api';
import { Badges } from './Badges';
import { ComingSoon } from './ComingSoon';
import { ErrorBoundary } from './ErrorBoundary';
import { Feedback } from './Feedback';
import { useAppFonts } from './fonts';
import { FirstCheck } from './FirstCheck';
import { Home } from './Home';
import { MicButton } from './MicButton';
import { ReadingScene } from './ReadingScene';
import { Results } from './Results';
import { SessionContext, type Session } from './session';
import { PracticeFeedback, Quiz } from './Story';
import { SendFailed, Waiting } from './Waiting';
import { WarmUp } from './WarmUp';
import { colors } from './theme';
import { startHash } from './config';
import { ReadGame } from './ReadGame';
import { configFor as trainFor } from './WordTrain';
import { configFor as starsFor } from './SentenceStars';

const learner: LearnerPayload = {
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

const home: HomeData = {
  learner,
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

const words: WordResult[] = [
  { text: 'cat', status: 'correct', heard: null },
  { text: 'dog', status: 'correct', heard: null },
  { text: 'pig', status: 'sub', heard: 'big' },
  { text: 'hen', status: 'correct', heard: null },
  { text: 'cow', status: 'unsure', heard: null },
  { text: 'bat', status: 'skip', heard: null },
  { text: 'rat', status: 'repeated', heard: null },
  { text: 'fox', status: 'mispronounced', heard: 'fix' },
];

type Scored = Extract<ReadingOutcome, { status: 'scored' }>;

const scored = (extra: Partial<Scored> = {}): Scored => ({
  status: 'scored',
  learner,
  accuracy: 75,
  wcpm: 40,
  levelBefore: 'Developing',
  levelAfter: 'Developing',
  levelChanged: false,
  levelWentUp: false,
  progress: { moved: 'up', stepChanged: true, stepAfter: 'Sentence Reader', have: 3, need: 3, nextStep: 'Story Reader' },
  pointsEarned: 33,
  wordBreakdown: words,
  extraWordsSaid: ['banana'],
  wordsToPractice: 4,
  wordCounts: { right: 4, total: 8, notSure: 1 },
  comprehension: {
    correctCount: 2,
    totalCount: 3,
    breakdown: [
      { question: 'What did the hen eat?', picked: 'Corn', correctAnswer: 'Corn', isCorrect: true },
      { question: 'Where did the pig sleep?', picked: 'Barn', correctAnswer: 'Mud', isCorrect: false },
      { question: 'Who ran away?', picked: 'The fox', correctAnswer: 'The fox', isCorrect: true },
    ],
  },
  newBadges: [
    { code: 'a', name: 'First Reading Star', description: '', icon: 'star' },
    { code: 'b', name: 'Sprout Reader', description: '', icon: 'plant' },
  ],
  ...extra,
});


/** The sample Home with the child on another step: path-1 (just starting), path-2, path-3 (two great readings in) and path-4 (the top). */
function homeFor(name: string): HomeData {
  const m = /^path-([1-4])$/.exec(name);
  if (!m) return home;
  const step = Number(m[1]);
  return {
    ...home,
    readingPath: home.readingPath.map((p) => ({ ...p, isCurrent: p.step === step })),
    pathProgress: step === 4 ? { have: 0, need: 3, atTop: true } : { have: step === 3 ? 2 : step === 1 ? 0 : 1, need: 3, atTop: false },
  };
}

const noop = () => {};

// Sample data for the read-aloud games: the same shape the server sends.
const trainConfig = (letters: boolean) =>
  trainFor({
    startLevel: 1,
    mode: letters ? 'letters' : 'words',
    cars: letters ? { '1': 4, '2': 5, '3': 6 } : { '1': 3, '2': 4, '3': 5 },
    levels: letters
      ? { '1': { struggling: [], favoured: [], fallback: ['s', 'a', 't', 'p', 'i', 'n'] }, '2': { struggling: [], favoured: [], fallback: ['s', 'a', 't', 'p', 'i', 'n', 'm', 'd'] }, '3': { struggling: [], favoured: [], fallback: ['s', 'a', 't', 'p', 'i', 'n', 'm', 'd', 'o', 'g'] } }
      : { '1': { struggling: ['hen'], favoured: [], fallback: ['cat', 'dog', 'sun', 'hat', 'pig', 'bed', 'cup', 'run', 'big'] }, '2': { struggling: [], favoured: [], fallback: ['frog', 'star', 'milk', 'nest', 'lamp', 'swim', 'gift', 'sock'] }, '3': { struggling: [], favoured: [], fallback: ['apple', 'table', 'happy', 'forest', 'purple', 'animal'] } },
  });
const starsConfig = starsFor({
  startLevel: 1,
  levels: {
    '1': { preferred: [], fallback: ['The cat sat.', 'A dog can run.', 'I see a pig.', 'The sun is hot.'] },
    '2': { preferred: [], fallback: ['The frog sat on a log.', 'We swim in the pond.', 'A fish can swim fast.'] },
    '3': { preferred: [], fallback: ['The farmer feeds the chickens and the pigs.', 'We plant seeds in the garden and water them every day.', 'The rain stopped, and a bright rainbow came out.'] },
  },
});

function screenFor(name: string) {
  switch (name) {
    case 'home':
    case 'path-1':
    case 'path-2':
    case 'path-3':
    case 'path-4':
      return <Home onStartReading={noop} />;
    case 'badges':
      return <Badges />;
    case 'results':
      return <Results firstName="Maria" title="Sounds at the Farm" outcome={scored()} onDone={noop} />;
    case 'results-low':
      return (
        <Results
          firstName="Maria"
          title="Sounds at the Farm"
          outcome={scored({
            progress: { moved: null, stepChanged: false, stepAfter: null, have: 1, need: 3, nextStep: 'Sentence Reader' },
            newBadges: [],
            comprehension: null,
            wordCounts: { right: 2, total: 8, notSure: 0 },
          })}
          onDone={noop}
        />
      );
    case 'check-intro':
      return <FirstCheck firstName="Maria" onDone={noop} />;
    case 'check-letters':
      return (
        <ReadingScene
          eyebrow="Passage 1"
          dots={{ done: 0, total: 3 }}
          direction="Say the name of each letter, one at a time."
          passage={{ kind: 'letters', letters: ['S', 'A', 'T', 'P', 'I', 'N'].map((c) => ({ upper: c, lower: c.toLowerCase() })) }}
          footer={<MicButton onRecorded={noop} />}
        />
      );
    case 'check-next':
      return <Feedback mood="happy" heading="Great job!" message="Let's see how the next one goes!" buttonLabel="Keep Going" onButton={noop} />;
    case 'check-unclear':
      return (
        <Feedback
          mood="sad"
          heading="Didn't quite catch that!"
          message="Find a quiet spot, hold the phone a little closer, and read it again."
          buttonLabel="Try Again"
          onButton={noop}
        />
      );
    case 'check-done':
      return <Feedback mood="happy" heading="You did it, Maria!" level="You're a Growing Reader!" badges={scored().newBadges} buttonLabel="Start Exploring" onButton={noop} />;
    case 'practice':
      return (
        <ReadingScene
          eyebrow="Sounds at the Farm"
          steps={{ labels: ['Listen', 'Your turn', 'Read for real'], active: 1 }}
          direction="Your turn. Read it out loud. This is a free try and nothing is saved. 1 try left."
          passage={{ kind: 'text', text: 'cat dog pig hen cow bat rat fox' }}
          speakText="cat dog pig hen cow bat rat fox"
          below={
            <PracticeFeedback
              result={{ status: 'scored', accuracy: 75, wordBreakdown: words, extraWordsSaid: [], wordsToPractice: 4, wordCounts: { right: 4, total: 8, notSure: 1 }, triesLeft: 1 }}
            />
          }
          footer={<MicButton onRecorded={noop} idleLabel="Tap the mic to try again" />}
          onBack={noop}
        />
      );
    case 'quiz':
      return (
        <Quiz
          questions={[
            { question: 'What did the hen eat?', choices: ['Corn', 'Fish', 'Cake', 'Rocks'] },
            { question: 'Who ran away?', choices: ['The fox', 'The pig'] },
          ]}
          onBack={noop}
          onSubmit={noop}
        />
      );
    case 'warmup':
      return <WarmUp letters={false} onDone={noop} />;
    case 'warmup-heard':
      return <WarmUp letters={false} onDone={noop} demo="heard" />;
    case 'warmup-quiet':
      return <WarmUp letters onDone={noop} demo="quiet" />;
    case 'train':
    case 'train-partial':
    case 'train-heard':
    case 'train-letters':
      return (
        <ReadGame
          key={name}
          config={trainConfig(name === 'train-letters')}
          firstName="Maria"
          onExit={noop}
          demo={name === 'train-partial' ? { matched: [true, false, true], tries: 1, note: 'Tara heard some of it. Try once more!' } : name === 'train-heard' ? { caught: true } : undefined}
        />
      );
    case 'stars':
    case 'stars-partial':
    case 'stars-heard':
      return (
        <ReadGame
          key={name}
          config={starsConfig}
          firstName="Maria"
          onExit={noop}
          demo={name === 'stars-partial' ? { matched: [true, true, false, true], tries: 1, note: 'Tara heard some of it. Try once more!' } : name === 'stars-heard' ? { caught: true, pos: 1 } : undefined}
        />
      );
    case 'waiting':
      return <Waiting />;
    case 'crash':
      // Shows the last-resort error screen: a part that throws once, and a button that gives the app a clean start.
      return (
        <ErrorBoundary onRestart={() => { healed = true; }}>
          <CrashOnce />
        </ErrorBoundary>
      );
    case 'failed':
      return <SendFailed message="We could not reach TaraBasa. Check the internet and try again." onRetry={noop} onReread={noop} />;
    default:
      return <ComingSoon title="Games are coming soon" text="Word games with Tara are on the way. For now, read a story and earn points." />;
  }
}

let healed = false;
/** Throws every time it is drawn until "Start Again" is pressed, like a bug that a fresh start clears. */
function CrashOnce() {
  if (!healed) throw new Error('Sample crash for the gallery');
  return <Waiting />;
}

export function Gallery() {
  const ready = useAppFonts();
  const name = startHash().replace(/^#gallery=?/, '') || 'home';
  const session = useMemo<Session>(
    () => ({ learner, home: homeFor(name), homeError: null, reloadHome: async () => {}, fontStep: 4, setFontStep: noop, setLearner: noop, signOut: noop }),
    [name],
  );
  if (!ready) return null;
  return (
    <SafeAreaProvider>
      <SessionContext.Provider value={session}>
        <View style={{ flex: 1, backgroundColor: colors.page }}>{screenFor(name)}</View>
      </SessionContext.Provider>
    </SafeAreaProvider>
  );
}
