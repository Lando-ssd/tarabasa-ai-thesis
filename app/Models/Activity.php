<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'created_by_teacher_id',
        'generation_id',
        'bundle_title',
        'grade_level',
        'competency',
        'competency_label',
        'activity_type',
        'purpose',
        'difficulty_tier',
        'variant_label',
        'topic',
        'teacher_notes',
        'title',
        'instructions',
        'passage_text',
        'reference_text',
        'word_count',
        'target_skills',
        'reading_features',
        'follow_up_questions',
        'status',
        'shared_to_repository',
    ];

    protected function casts(): array
    {
        return [
            'target_skills' => 'array',
            'reading_features' => 'array',
            'follow_up_questions' => 'array',
            'shared_to_repository' => 'boolean',
        ];
    }

    public function teacher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by_teacher_id');
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ActivityAssignment::class);
    }

    public function repositoryListing(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OpenRepositoryListing::class);
    }

    public function readingSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    /**
     * Shared by ActivityController (Teacher-authored) and
     * LearnerDiagnosticController (system-generated diagnostic passages)
     * — both create real Activity rows from the exact same
     * gemini_activity_gen bundle response shape, just with different
     * fixed attributes (teacher/topic vs. none/purpose=diagnostic).
     * $baseAttributes must already include a resolved competency_label.
     */
    public static function createManyFromBundle(array $bundleData, array $baseAttributes): \Illuminate\Support\Collection
    {
        $generationId = $bundleData['generation_id'] ?? (string) \Illuminate\Support\Str::uuid();
        $created = collect();

        foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $apiKey => $tierLabel) {
            foreach ($bundleData['levels'][$apiKey] ?? [] as $variant) {
                $created->push(self::create(array_merge($baseAttributes, [
                    'generation_id' => $generationId,
                    'bundle_title' => $bundleData['bundle_title'] ?? null,
                    'difficulty_tier' => $tierLabel,
                    'variant_label' => $variant['variant_label'] ?? null,
                    'title' => $variant['title'] ?? $bundleData['bundle_title'] ?? 'Untitled Activity',
                    'instructions' => $variant['instructions'] ?? '',
                    'passage_text' => $variant['display_text'] ?? '',
                    'reference_text' => $variant['reference_text'] ?? null,
                    'word_count' => $variant['word_count'] ?? null,
                    'target_skills' => $variant['target_skills'] ?? [],
                    'reading_features' => $variant['reading_features'] ?? [],
                    'follow_up_questions' => $variant['follow_up_questions'] ?? [],
                    'status' => 'Draft',
                ])));
            }
        }

        return $created;
    }

    /**
     * The real Learner-side access boundary — shared by every Learner-
     * facing controller that touches an Activity, so this security-
     * critical check only ever lives in one place. (A near-identical
     * copy of this once had a real bug: an unclassed Learner could open
     * any learner-direct assignment because Laravel silently turns
     * where('class_id', null) into "class_id IS NULL" — fixed here by
     * only adding that clause when the Learner actually has a class_id.)
     */
    public function isAccessibleByLearner(Learner $learner): bool
    {
        if ($this->status !== 'Approved') {
            return false;
        }

        return $this->isAssignedToLearner($learner) || $this->isUnlockedForLearner($learner);
    }

    public function isAssignedToLearner(Learner $learner): bool
    {
        return $this->assignments()
            ->where(function ($query) use ($learner) {
                $query->where('learner_id', $learner->id);

                if ($learner->class_id !== null) {
                    $query->orWhere('class_id', $learner->class_id);
                }

                if ($learner->schoolClass?->group_tag) {
                    $query->orWhere(function ($groupQuery) use ($learner) {
                        $groupQuery->where('group_tag', $learner->schoolClass->group_tag)
                            ->where('assigned_by_teacher_id', $learner->schoolClass->teacher_id);
                    });
                }
            })
            ->exists();
    }

    /**
     * Open Repository — a Parent unlocked this specific Activity for
     * this specific Learner (never a flat platform subscription).
     */
    public function isUnlockedForLearner(Learner $learner): bool
    {
        return $this->repositoryListing !== null
            && $this->repositoryListing->isUnlockedFor($learner->id);
    }

    /**
     * Learner Actor Prompt Step 6: initiatedBy is determined by HOW this
     * activity was reached, never guessed — 'Teacher' if it came from an
     * assignment, 'Parent' if it came from an unlocked repository item.
     * Assignment wins if somehow both are true (a Teacher assigning
     * their own shared content to the same Learner who also unlocked
     * it) since that's the more specific, more recent intent.
     */
    public function initiatedBySourceFor(Learner $learner): string
    {
        return $this->isAssignedToLearner($learner) ? 'Teacher' : 'Parent';
    }

    /**
     * "My Bookshelf" access boundary — deliberately NOT
     * isAccessibleByLearner(). That check is about whether a Teacher
     * assignment or Parent unlock is currently live; Bookshelf is a
     * Learner's own reading history, which should stay revisitable even
     * if the original assignment/unlock has since gone away (a Teacher
     * un-assigning something doesn't erase that the child already read
     * it). The real, correct question here is simply: did this Learner
     * genuinely complete a real Practice reading of this Activity before?
     */
    public function hasCompletedPracticeReadingFor(Learner $learner): bool
    {
        return $this->readingSessions()
            ->where('learner_id', $learner->id)
            ->where('session_type', 'Practice')
            ->exists();
    }
}
