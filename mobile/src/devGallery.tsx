// Developer only. In the browser preview, open  http://localhost:8081/#gallery=<name>  to see one screen with sample
// data at any phone size, without a server, a microphone or a child. It is never reached in a phone build (App.tsx
// guards it with __DEV__ and the web platform). Names: home, badges, results, results-low, check-intro, check-letters,
// check-next, check-unclear, check-done, warmup, warmup-heard, warmup-quiet, practice, quiz, waiting, failed, soon.
import { useMemo } from 'react';
import { View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import type { Home as HomeData, LearnerPayload, ReadingOutcome, WordResult } from './api';
import { Badges } from './Badges';
import { ComingSoon } from './ComingSoon';
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
    { step: 1, name: 'Letter Explorer', skill: '', blurb: '', isCurrent: false },
    { step: 2, name: 'Word Builder', skill: '', blurb: '', isCurrent: true },
    { step: 3, name: 'Sentence Reader', skill: '', blurb: '', isCurrent: false },
    { step: 4, name: 'Story Reader', skill: '', blurb: '', isCurrent: false },
  ],
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

const noop = () => {};

function screenFor(name: string) {
  switch (name) {
    case 'home':
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
    case 'waiting':
      return <Waiting />;
    case 'failed':
      return <SendFailed message="We could not reach TaraBasa. Check the internet and try again." onRetry={noop} onReread={noop} />;
    default:
      return <ComingSoon title="Games are coming soon" text="Word games with Tara are on the way. For now, read a story and earn points." />;
  }
}

export function Gallery() {
  const ready = useAppFonts();
  const name = (typeof window !== 'undefined' ? window.location.hash : '').replace(/^#gallery=?/, '') || 'home';
  const session = useMemo<Session>(
    () => ({ learner, home, homeError: null, reloadHome: async () => {}, fontStep: 4, setFontStep: noop, setLearner: noop, signOut: noop }),
    [],
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
