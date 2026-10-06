<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;

/**
 * The first-login check's curated bank as real Activity rows (purpose = 'diagnostic').
 *
 * The texts live in config (config/diagnostic_bank.php and the letters in
 * config/diagnostic.php) so they are reviewed in one place and reach production
 * with the code, with no seeder to remember to run. The rows are made the first time a
 * check needs them, and found again by their exact text afterwards: editing a text in
 * config makes a NEW row and leaves the old one alone, because readings already saved
 * against it must keep pointing at what the child actually read.
 *
 * Nothing here calls the AI generator.
 */
class DiagnosticBank
{
    /** Marks the bank's rows (the letters keep their older title, see letterRows()). */
    public const BUNDLE_TITLE = 'Reading check';

    private const LETTERS_BUNDLE_TITLE = 'Letter Check';

    /**
     * Every rung's activity ids, in the order they are written in config.
     *
     * @return array<string, list<int>>
     */
    public function idsByRung(): array
    {
        $ids = $this->collect(create: false);

        if ($ids === null) {
            // Something is missing. Make it under a lock so two children starting a check at
            // the same moment cannot each make a copy.
            $lock = Cache::lock('diagnostic-bank-build', 20);
            try {
                $lock->block(10);
            } catch (\Throwable) {
                // Could not get the lock in time: carry on anyway. At worst a duplicate row is
                // made, and the check works the same with either copy.
            }

            try {
                $ids = $this->collect(create: true);
            } finally {
                optional($lock)->release();
            }
        }

        return $ids;
    }

    /**
     * @return array<string, list<int>>|null null when $create is false and a row is missing
     */
    private function collect(bool $create): ?array
    {
        $existing = Activity::where('purpose', 'diagnostic')
            ->whereIn('bundle_title', [self::BUNDLE_TITLE, self::LETTERS_BUNDLE_TITLE])
            ->get()
            ->groupBy(fn (Activity $a) => $a->bundle_title.'|'.$a->variant_label.'|'.$a->reference_text);

        $competencies = config('activity_competencies.competencies');
        $ids = [];

        foreach (config('diagnostic.ladder') as $rung => $def) {
            $ids[$rung] = [];

            foreach ($this->rows($rung, $def) as $row) {
                $key = $row['bundle_title'].'|'.$row['variant_label'].'|'.$row['reference_text'];
                $activity = $existing->get($key)?->first();

                if ($activity === null) {
                    if (! $create) {
                        return null;
                    }

                    $activity = Activity::create($row + [
                        'created_by_teacher_id' => null,
                        'purpose' => 'diagnostic',
                        'check_rung' => $rung,
                        'grade_level' => 'Grade '.$def['grade'],
                        'competency' => $def['competency'],
                        'competency_label' => $competencies[$def['competency']]['label'] ?? 'Reading',
                        'activity_type' => $def['activity_type'],
                        'difficulty_tier' => ucfirst($def['tier']),
                        'ai_difficulty_tier' => ucfirst($def['tier']),
                        'curriculum_code' => array_key_first($def['codes']),
                        'target_skills' => collect($def['codes'])->map(fn ($text, $code) => "{$code} {$text}")->values()->all(),
                        'follow_up_questions' => [],
                        'status' => 'Draft',
                    ]);
                } elseif ($create && ($activity->check_rung === null || $activity->curriculum_code === null)) {
                    // A letters row made before these columns existed: fill them in once.
                    $activity->update([
                        'check_rung' => $rung,
                        'curriculum_code' => array_key_first($def['codes']),
                    ]);
                }

                $ids[$rung][] = $activity->id;
            }
        }

        return $ids;
    }

    /**
     * The rows one rung needs, as the columns that identify and describe them.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $rung, array $def): array
    {
        if ($def['kind'] === 'letters') {
            return array_map(fn (array $set) => [
                'bundle_title' => self::LETTERS_BUNDLE_TITLE,
                'variant_label' => $set['label'],
                'reference_text' => strtolower(implode(' ', $set['letters'])),
                'title' => 'Letter Check '.$set['label'],
                'instructions' => 'Say the name of each letter.',
                'passage_text' => implode(' ', $set['letters']),
                'word_count' => count($set['letters']),
                'reading_features' => ['Single letters'],
            ], config('diagnostic.letters.sets'));
        }

        $rungConfig = config("diagnostic_bank.{$rung}");
        $rows = [];

        foreach ($rungConfig['items'] as $n => $item) {
            $rows[] = [
                'bundle_title' => self::BUNDLE_TITLE,
                'variant_label' => $rung.':'.($n + 1),
                'reference_text' => $this->referenceText($item['text']),
                'title' => $item['title'],
                'instructions' => $item['direction'] ?? $rungConfig['direction'],
                'passage_text' => $item['text'],
                'word_count' => Activity::countWords($item['text']),
                'reading_features' => [$item['kind']],
            ];
        }

        return $rows;
    }

    /** The text the scorer compares against: the displayed text with its spacing tidied. */
    private function referenceText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
