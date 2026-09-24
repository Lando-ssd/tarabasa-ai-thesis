<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;

/**
 * The one place that answers "which MATATAG subdomain and competency code
 * does this activity count toward?", shared by Reading-api v4 (which
 * validates both on every /analyze call) and the Adaptive_Recommendator v2
 * (which keeps a proficiency per subdomain). Two services asking the same
 * question must never get two different answers, so it lives here once.
 *
 * The subdomain comes from config/matatag_subdomains.php (see the
 * PROVISIONAL note there: the generator only tags a grouped competency
 * today). The competency code comes from the generator's own curriculum
 * alignment for the activity's grade + competency, cached a day because it
 * is fixed curriculum data.
 */
class MatatagAlignmentResolver
{
    /**
     * @return list<string> The subdomains valid for this grade, in the
     *                      recommender's own order.
     */
    public function gradeSubdomains(int $grade): array
    {
        return config("matatag_subdomains.grade_subdomains.{$grade}", []);
    }

    public function gradeOf(Activity $activity): int
    {
        return (int) substr((string) $activity->grade_level, 6);
    }

    /**
     * The subdomain an activity counts toward, or null when the app has no
     * honest mapping for it (an unknown competency), in which case it is not
     * sent to the adaptive engine at all.
     */
    public function subdomainFor(Activity $activity): ?string
    {
        $type = strtolower((string) $activity->activity_type);

        $subdomain = config("matatag_subdomains.activity_type_subdomain.{$type}")
            ?? config("matatag_subdomains.activity_subdomain.{$activity->competency}");

        return in_array($subdomain, $this->gradeSubdomains($this->gradeOf($activity)), true) ? $subdomain : null;
    }

    /**
     * Grade + subdomain + competency code in the shape Reading-api v4
     * validates. If the generator can't be reached, a valid default is used
     * so a reading is never blocked by this lookup; that fallback is cached
     * only briefly so the real codes are picked up as soon as they're
     * available again.
     *
     * @return array{grade: int, subdomain: string, competency_code: string}
     */
    public function contextFor(Activity $activity): array
    {
        $grade = $this->gradeOf($activity);
        $competency = (string) $activity->competency;
        $subdomain = $this->subdomainFor($activity) ?? 'Comprehending and Analyzing Text';

        // The first-login letters rung is not a generated activity, so there
        // is nothing to look up: it is the curriculum's own letter competency.
        if ($activity->isLetterCheck()) {
            return [
                'grade' => $grade,
                'subdomain' => $subdomain,
                'competency_code' => config('diagnostic.letters.competency_code'),
            ];
        }

        $records = $this->recordsFor($grade, $competency);

        // Prefer an official record in the same subdomain. When the curriculum
        // has none there (fluency is aligned to Comprehending and Analyzing
        // Text while it counts toward Phonics and Word Study), fall back to the
        // first official code for the competency so the tag is still a real
        // MATATAG code, or to a plain marker when nothing could be looked up.
        $match = collect($records)->firstWhere('subdomain', $subdomain) ?? ($records[0] ?? null);

        return [
            'grade' => $grade,
            'subdomain' => $subdomain,
            'competency_code' => $match['code'] ?? 'UNMAPPED',
        ];
    }

    /**
     * @return list<array{code: string, subdomain: string}>
     */
    private function recordsFor(int $grade, string $competency): array
    {
        $cacheKey = "matatag_records:{$grade}:{$competency}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $records = app(ActivityAiClient::class)->alignmentRecords($grade, $competency);

        if ($records !== null) {
            Cache::put($cacheKey, $records, now()->addDay());

            return $records;
        }

        // Remember the miss for a few minutes so a generator that is asleep
        // or down doesn't add its timeout to every single reading.
        Cache::put($cacheKey, [], now()->addMinutes(5));

        return [];
    }
}
