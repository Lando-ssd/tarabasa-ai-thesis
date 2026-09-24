<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

/**
 * A Learner never has a password or email (Learner Actor Prompt, Rule 1)
 * but still needs its own login session, separate from the 'web' guard
 * Users authenticate on — see config/auth.php's 'learner' guard. Kept as
 * a plain Model implementing Authenticatable directly (not extending
 * Laravel's Foundation\Auth\User base) so it doesn't pick up unrelated
 * concerns like password-reset or email verification.
 *
 * HasApiTokens (Sanctum) is the mobile app's auth mechanism — a real
 * bearer token issued at login, completely independent of the 'learner'
 * session guard the web app uses. Works on any Eloquent model, not just
 * a "User" — Sanctum just needs createToken()/tokens() from the trait.
 */
class Learner extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens;

    public $timestamps = false;

    protected $fillable = [
        'learner_code',
        'class_id',
        'first_name',
        'middle_name',
        'last_name',
        'grade_level',
        'pin',
        'avatar_id',
        'avatar_photo_path',
        'mastery_level',
        'competency_states',
        'next_recommended_competency',
        'next_recommended_difficulty',
        'subdomain_states',
        'next_recommended_subdomain',
        'learning_style',
        'points',
        'streak',
        'status',
        'reading_font_step',
    ];

    protected $hidden = [
        'pin',
    ];

    protected function casts(): array
    {
        return [
            // A Learner's PIN is a real credential (Learner Actor Prompt,
            // Step 1) even though it's only 4 digits — hashed exactly like
            // a User's password, never stored or compared in plain text.
            'pin' => 'hashed',
            // Adaptive_Recommendator's own CurrentState shape, stored
            // verbatim — see the migration comment for why this repo owns
            // this state at all.
            'competency_states' => 'array',
            'subdomain_states' => 'array',
        ];
    }

    /**
     * The Authenticatable contract expects a "password" concept — a
     * Learner's real credential is its hashed PIN instead.
     */
    public function getAuthPassword(): string
    {
        return $this->pin;
    }

    public function schoolClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ParentAccount::class, 'parent_learners', 'learner_id', 'parent_id')
            ->withPivot(['relationship', 'is_creator', 'linked_at']);
    }

    public function promotionRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PromotionRecord::class);
    }

    public function readingSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    /**
     * LearnerHistory_Addition.txt Part 1 — computed from PromotionRecord
     * alone, no new table. A Pending record where THIS Learner is the
     * subject (released but not yet claimed) never needs handling here:
     * that means class_id is null, so this Learner isn't showing inside
     * any Teacher's Class Management roster to begin with.
     */
    public function promotionHistorySummary(): array
    {
        // Reads from the already-loaded relation (in-memory filter/sort)
        // rather than firing a fresh query, so eager-loading
        // `learners.promotionRecords.releasedFromClass` in Class
        // Management's index actually avoids N+1 across a whole roster.
        $claimed = $this->promotionRecords
            ->where('status', 'Claimed')
            ->sortBy('claimed_at')
            ->values();

        if ($claimed->isEmpty()) {
            return ['headline' => 'New to the system — no prior grade history.', 'chain' => []];
        }

        $latest = $claimed->last();
        $headline = "Promoted from a previous class — now in {$latest->next_grade} since {$latest->claimed_at->format('M Y')}.";

        $chain = $claimed->count() > 1
            ? $claimed->map(fn (PromotionRecord $r) => "{$r->releasedFromGrade()} → {$r->next_grade} (claimed {$r->claimed_at->format('M Y')})")->all()
            : [];

        return ['headline' => $headline, 'chain' => $chain];
    }

    /**
     * LearnerHistory_Addition.txt Part 2 — the Learner's ENTIRE lifetime
     * session history, not scoped to the current Teacher or school year,
     * so a new Teacher after a promotion sees where this child actually
     * stands instead of starting blind. Always the real three-tier label,
     * never collapsed into a subjective "Good"/"Weak" word.
     */
    public function proficiencyTrajectorySummary(): string
    {
        $sessions = $this->readingSessions->sortBy('timestamp')->values();

        if ($sessions->isEmpty()) {
            return 'No reading sessions recorded yet. Starting level: '.($this->mastery_level ?? 'New');
        }

        $startedAt = $sessions->first()->level_before ?? 'New';
        $currentlyAt = $sessions->last()->level_after ?? ($this->mastery_level ?? 'New');

        return "Proficiency: started at {$startedAt} → currently {$currentlyAt}";
    }

    /**
     * The subdomains the app can actually give a learner practice in today,
     * from config/matatag_subdomains.php (see the PROVISIONAL note there).
     * The recommender knows five; showing a child a "Sounds and Rhymes" step
     * they could never start would be a fake promise, so only these (plus
     * anything they already have a real proficiency in) are shown.
     *
     * @return list<string>
     */
    public function practiceableSubdomains(): array
    {
        return array_values(array_unique(array_values(config('matatag_subdomains.activity_subdomain', []))));
    }

    /**
     * The subdomain this learner should practice next, for display and for
     * the activity picker. It is the recommender's own recommendation when
     * the app has activities for it. When the recommender points at a
     * subdomain the app has no content for yet (it always starts with the
     * ones nobody has assessed), fall back to the practiceable subdomain the
     * learner is weakest in among those they have actually been measured on,
     * and to the diagnostic's own subdomain before anything is measured.
     * Returns null before the recommender has produced any state at all.
     */
    public function focusSubdomain(): ?string
    {
        if ($this->subdomain_states === null) {
            return null;
        }

        $practiceable = $this->practiceableSubdomains();
        $recommended = $this->next_recommended_subdomain;

        if ($recommended && in_array($recommended, $practiceable, true)) {
            return $recommended;
        }

        $measured = collect($practiceable)
            ->filter(fn (string $name) => isset($this->subdomain_states[$name]['proficiency']))
            ->sortBy(fn (string $name) => $this->subdomain_states[$name]['proficiency']);

        return $measured->first() ?? config('matatag_subdomains.diagnostic_subdomain');
    }

    /**
     * The difficulty to pair with focusSubdomain(): the recommender's own
     * when the focus is its recommendation, otherwise that subdomain's own
     * current difficulty from the learner's state (easy when never measured).
     */
    public function focusDifficulty(): ?string
    {
        $focus = $this->focusSubdomain();

        if ($focus === null) {
            return null;
        }

        if ($focus === $this->next_recommended_subdomain) {
            return $this->next_recommended_difficulty;
        }

        return $this->subdomain_states[$focus]['difficulty'] ?? 'easy';
    }

    /**
     * Makes the Adaptive_Recommendator engine's per-subdomain state
     * (proficiency/difficulty/confidence, real 0-100 and easy/medium/hard
     * values) presentable to a young reader (the Learner dashboard) and to a
     * Teacher or Parent (Analytics, Progress) without each place needing its
     * own copy of the subdomain-to-friendly-name mapping. Returns [] when
     * the learner has no subdomain_states yet, so callers render an honest
     * "not started" state rather than a fabricated one.
     */
    public function subdomainProgressSummary(): array
    {
        if ($this->subdomain_states === null) {
            return [];
        }

        $grade = (int) substr((string) $this->grade_level, 6);
        $practiceable = $this->practiceableSubdomains();
        $focus = $this->focusSubdomain();
        $labels = config('matatag_subdomains.labels');
        $difficultyWords = config('matatag_subdomains.difficulty_words');

        return collect(config("matatag_subdomains.grade_subdomains.{$grade}", []))
            ->filter(fn (string $name) => in_array($name, $practiceable, true) || isset($this->subdomain_states[$name]['proficiency']))
            ->map(function (string $name) use ($labels, $difficultyWords, $focus) {
                $state = $this->subdomain_states[$name] ?? [];
                $difficulty = $state['difficulty'] ?? null;

                return [
                    'key' => $name,
                    'label' => $labels[$name]['label'] ?? $name,
                    'description' => $labels[$name]['description'] ?? '',
                    'proficiency' => $state['proficiency'] ?? null,
                    'difficulty' => $difficulty,
                    'difficultyWord' => $difficulty ? ($difficultyWords[$difficulty] ?? null) : null,
                    'isUpNext' => $name === $focus,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The one shared source for "how much real reading happened this
     * week" — used by both Weekly Goal (a single count vs. a target) and
     * the Growth panel (the same sessions bucketed by weekday), so the
     * two panels can never quietly disagree about which sessions count
     * or where the week boundary falls. Practice only, same convention
     * as Analytics/Badges/Bookshelf — a Diagnostic passage is system-
     * generated throwaway content, never a "story read" a child chose.
     * Carbon's default week start (Monday, since APP_LOCALE has no
     * week_starts_at override) is used as-is rather than hardcoded.
     */
    public function thisWeeksPracticeReadingSessions(): \Illuminate\Support\Collection
    {
        return ReadingSession::where('learner_id', $this->id)
            ->where('session_type', 'Practice')
            ->whereBetween('timestamp', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();
    }

    /**
     * "My Bookshelf" — Activities this Learner has genuinely completed a
     * real Practice reading of before, one row per distinct Activity
     * (real times-read count, most recent date, best accuracy). Moved
     * here from BookshelfController::index() when the Bookshelf list
     * moved from its own page onto the single-dashboard collage — the
     * real re-read task screens (BookshelfController::reread()/
     * submitReread()) still use Activity::hasCompletedPracticeReadingFor()
     * as their own, separately-scoped access check.
     */
    public function bookshelfBooks(): \Illuminate\Support\Collection
    {
        return ReadingSession::where('learner_id', $this->id)
            ->where('session_type', 'Practice')
            ->with('activity')
            ->get()
            ->filter(fn (ReadingSession $session) => $session->activity !== null)
            ->groupBy('activity_id')
            ->map(fn ($sessions) => [
                'activity' => $sessions->first()->activity,
                'timesRead' => $sessions->count(),
                'mostRecentDate' => $sessions->max('timestamp'),
                'bestAccuracy' => $sessions->max('accuracy_percent'),
            ])
            ->sortByDesc('mostRecentDate')
            ->values();
    }

    /**
     * Practice Games: Word Builder and Letter Match are both real
     * foundational skill practice (spelling, letter and sound recognition);
     * neither one measures comprehension, so this deliberately never claims a
     * connection to it. Only emphasizes a specific game when the learner's
     * current adaptive focus is one of the foundational subdomains; never a
     * hard gate (both games stay fully playable regardless), just which one
     * the Games hub leads with. Returns null when there's nothing honest to
     * recommend (pre-diagnostic, or the current focus is comprehension) so the
     * caller can render a neutral state rather than a fabricated one.
     */
    public function recommendedGameFocus(): ?array
    {
        $foundational = [
            'Phonological Awareness',
            'Phonics and Word Study',
            'Vocabulary and Word Knowledge',
            'Book and Print Knowledge',
        ];

        if (! in_array($this->focusSubdomain(), $foundational, true)) {
            return null;
        }

        // A real, disclosed secondary signal, not a coin flip: a Learner
        // with real struggling words gets Word Builder, since it's the
        // one game that directly drills their own actual weak words —
        // Letter Match is the safer foundational default otherwise.
        $hasStrugglingWords = PersonalWordBank::where('learner_id', $this->id)
            ->where('mastery_status', 'Struggling')
            ->exists();

        return ['game' => $hasStrugglingWords ? 'word-builder' : 'letter-match'];
    }

    /**
     * The passage-text size step (1=Small ... 5=Extra Large) actually
     * used for this Learner — their own explicit choice if they've ever
     * made one, otherwise a sensible grade-based starting point (younger
     * grades default larger). The +/- control on the reading screens
     * only ever writes an explicit value into reading_font_step; this
     * method is the one place that resolves "what size right now,"
     * shared by both reading screens instead of duplicating the
     * grade-to-default mapping in two places.
     */
    public function effectiveReadingFontStep(): int
    {
        if ($this->reading_font_step !== null) {
            return $this->reading_font_step;
        }

        return match ((int) substr($this->grade_level, 6)) {
            1 => 4,
            3 => 2,
            default => 3,
        };
    }

    /**
     * "TB-48213" style — generated once at creation, retried on the rare
     * collision (learner_code is UNIQUE in the schema).
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'TB-' . random_int(10000, 99999);
        } while (self::where('learner_code', $code)->exists());

        return $code;
    }
}
