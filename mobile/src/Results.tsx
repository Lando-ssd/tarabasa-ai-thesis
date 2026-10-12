import { View } from 'react-native';
import type { ReadingOutcome } from './api';
import { Feedback, type Stat } from './Feedback';
import { colors } from './theme';
import { Icon, Panel, Small, H2 } from './ui';
import { WordReview } from './WordReview';

type Scored = Extract<ReadingOutcome, { status: 'scored' }>;

/**
 * The result of a real reading. Always the happy star, even after a hard reading. A child sees how many words went
 * right and the points; the percentage and the speed stay on the teacher's and the parent's screens. A level that
 * moved says so; one that stayed the same says nothing.
 */
export function Results({ firstName, title, outcome, onDone }: { firstName: string; title: string; outcome: Scored; onDone: () => void }) {
  const p = outcome.progress;
  let level: string | null = null;
  if (p && p.moved === 'up') {
    level = p.stepChanged ? `Level up! You are now a ${p.stepAfter}` : `You got stronger as a ${p.stepAfter}!`;
  } else if (p && p.moved === null && p.have > 0 && p.nextStep !== null) {
    level = `Strong readings toward growing: ${p.have} of ${p.need}`;
  } else if (!p && outcome.levelChanged) {
    level = outcome.levelWentUp ? `Level up! You are now ${outcome.levelAfter}` : `Your level is now ${outcome.levelAfter}`;
  }

  const counts = outcome.wordCounts;
  const stats: Stat[] = [];
  if (counts) {
    stats.push({ value: `${counts.right} of ${counts.total}`, label: 'Words read right', icon: 'target', tone: colors.ok });
    if (counts.notSure > 0) stats.push({ value: String(counts.notSure), label: 'Not sure', icon: 'info', tone: colors.slate });
  } else {
    stats.push({ value: `${Math.round(outcome.accuracy)}%`, label: 'Accuracy', icon: 'target', tone: colors.ok });
  }
  stats.push({ value: `+${outcome.pointsEarned}`, label: 'Points', icon: 'star', tone: colors.goldDeep });

  const quiz = outcome.comprehension;

  return (
    <Feedback
      mood="happy"
      heading={`Great reading, ${firstName}!`}
      subline={`You read "${title}".`}
      level={level}
      stats={stats}
      badges={outcome.newBadges}
      buttonLabel="Done"
      onButton={onDone}
      details={
        <View style={{ gap: 12 }}>
          <Panel style={{ gap: 8 }}>
            <Small>Your words</Small>
            <WordReview words={outcome.wordBreakdown} extra={outcome.extraWordsSaid} />
          </Panel>
          {quiz ? (
            <Panel style={{ gap: 8 }}>
              <H2>
                You got {quiz.correctCount} out of {quiz.totalCount} right!
              </H2>
              {quiz.breakdown.map((q, i) => (
                <View key={i} style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-start' }}>
                  <Icon name={q.isCorrect ? 'check-circle' : 'lightbulb'} size={22} color={q.isCorrect ? colors.ok : colors.warn} />
                  <Small style={{ flex: 1, color: colors.ink }}>
                    {q.question}
                    {q.isCorrect ? '' : ` The answer was: ${q.correctAnswer}`}
                  </Small>
                </View>
              ))}
            </Panel>
          ) : null}
        </View>
      }
    />
  );
}
