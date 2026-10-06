<?php

namespace App\Services;

use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Support\ErrorPatterns;
use App\Support\LearnerClock;
use App\Support\ReadingLevel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The class-level picture a teacher sees first on Analytics: how the classes are reading, in the
 * same Phil-IRI levels teachers report on, what was missed most, and what each assignment produced.
 * Teacher-assigned and parent-started readings are kept apart, and the one-time first reading
 * check never counts as practice. Everything is limited to the Teacher's own learners.
 */
class TeacherInsights
{
    // 'all' is the last six months, not literally everything: a class can have thousands of readings
    // (each with its word by word record) and loading every one makes the page slower every month.
    public const PERIODS = ['week' => 'This week', 'month' => 'This month', 'all' => 'Last 6 months'];

    public function __construct(private TeacherAlerts $alerts)
    {
    }

    /**
     * The classes in scope: one of the Teacher's own classes when an id is given, otherwise every
     * class of the school year in progress.
     *
     * @return Collection<int, SchoolClass>
     */
    public function classesFor(Teacher $teacher, ?int $classId): Collection
    {
        $own = SchoolClass::where('teacher_id', $teacher->id)->orderByDesc('school_year')->orderBy('name')->get();

        if ($classId !== null && ($one = $own->firstWhere('id', $classId))) {
            return collect([$one]);
        }

        return $own->where('school_year', SchoolClass::currentSchoolYear())->values();
    }

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: ?Carbon} start of the period, start of the one before, now (all UTC) */
    public function window(string $period): array
    {
        $now = LearnerClock::now();

        return match ($period) {
            'week' => [LearnerClock::toStorage($now->copy()->startOfWeek()), LearnerClock::toStorage($now->copy()->startOfWeek()->subWeek()), now()],
            'month' => [LearnerClock::toStorage($now->copy()->startOfMonth()), LearnerClock::toStorage($now->copy()->startOfMonth()->subMonth()), now()],
            default => [LearnerClock::toStorage($now->copy()->subMonths(6)->startOfDay()), null, now()],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(Teacher $teacher, Collection $classes, string $period): array
    {
        $learners = Learner::whereIn('class_id', $classes->pluck('id'))->with('schoolClass')->orderBy('first_name')->get();
        [$from, $before, $now] = $this->window($period);

        $sessions = $this->sessions($learners, $from, $now);
        $previous = ($from && $before) ? $this->sessions($learners, $before, $from) : collect();

        $avg = $sessions->isNotEmpty() ? $sessions->avg('accuracy_percent') : null;
        $prevAvg = $previous->isNotEmpty() ? $previous->avg('accuracy_percent') : null;

        // Reading levels in Phil-IRI terms, in the order a teacher reports them.
        $bands = ['non' => 0, 'frustration' => 0, 'instructional' => 0, 'independent' => 0, 'unchecked' => 0];
        foreach ($learners as $learner) {
            $bands[ReadingLevel::band($learner)]++;
        }

        // Words missed most (a skipped word or a different word; "not sure" words never count).
        $missed = $sessions->flatMap(fn ($s) => collect((array) ($s->word_feedback ?? []))
            ->filter(fn ($w) => ErrorPatterns::classify((array) $w) !== null)
            ->pluck('reference')->filter()->map(fn ($w) => strtolower($w)))
            ->countBy()->sortDesc()->take(5);

        // The kind of mistake across the group, and how many children it shows up for.
        $patterns = [];
        foreach ($sessions->groupBy('learner_id') as $learnerId => $own) {
            $kinds = [];
            foreach ($own as $s) {
                foreach ((array) ($s->word_feedback ?? []) as $w) {
                    if ($kind = ErrorPatterns::classify((array) $w)) {
                        $kinds[$kind] = ($kinds[$kind] ?? 0) + 1;
                    }
                }
            }
            foreach ($kinds as $kind => $n) {
                $patterns[$kind]['misses'] = ($patterns[$kind]['misses'] ?? 0) + $n;
                $patterns[$kind]['learners'][$learnerId] = true;
            }
        }
        $patternRows = collect($patterns)
            ->map(fn ($p, $kind) => ['key' => $kind, 'label' => ErrorPatterns::CATEGORIES[$kind]['label'], 'misses' => $p['misses'], 'learners' => count($p['learners'])])
            ->sortByDesc('learners')->take(3)->values();

        $needSupport = $this->alerts->forTeacher($teacher, withSuggestions: false)
            ->where('kind', 'support')
            ->filter(fn ($a) => $learners->contains('id', $a['learner']->id))
            ->count();

        return [
            'learners' => $learners,
            'classes' => $classes,
            'readings' => $sessions->count(),
            'bySource' => collect(['Teacher', 'Parent'])->mapWithKeys(fn ($src) => [$src => $sessions->where('initiated_by', $src)->count()]),
            'avg' => $avg === null ? null : (int) round($avg),
            'delta' => ($avg !== null && $prevAvg !== null) ? (int) round($avg - $prevAvg) : null,
            'needSupport' => $needSupport,
            'bands' => $bands,
            'checked' => $learners->count() - $bands['unchecked'],
            'missedWords' => $missed,
            'patterns' => $patternRows,
            'assignments' => $this->assignmentRows($teacher, $classes, $learners, 8),
        ];
    }

    /**
     * What each assignment produced: for every activity the Teacher gave to the classes in scope,
     * who it was for, how many of them have read it since, and their average. This is where the
     * results of assigned work show up.
     *
     * @return Collection<int, array{activity: \App\Models\Activity, for: string, total: int, done: int, avg: ?int}>
     */
    public function assignmentRows(Teacher $teacher, Collection $classes, Collection $learners, int $limit): Collection
    {
        $classIds = $classes->pluck('id');
        $tags = $classes->pluck('group_tag')->filter()->unique();

        $rows = ActivityAssignment::where('assigned_by_teacher_id', $teacher->id)
            ->with(['activity', 'learner', 'schoolClass'])
            ->orderByDesc('assigned_at')->orderByDesc('id')
            ->limit(60)
            ->get()
            ->filter(fn ($a) => $a->activity && (
                ($a->learner_id && $learners->contains('id', $a->learner_id))
                || ($a->class_id && $classIds->contains($a->class_id))
                || ($a->group_tag && $tags->contains($a->group_tag))
            ))
            ->take($limit);

        return $rows->map(function (ActivityAssignment $a) use ($learners) {
            // The learners this assignment reaches (the same rule a child's own reading list uses).
            $targets = $learners->filter(function (Learner $l) use ($a) {
                if ($a->learner_id) {
                    return $l->id === $a->learner_id;
                }
                if ($a->class_id) {
                    return $l->class_id === $a->class_id && ($a->reading_band === null || ReadingLevel::groupOf($l) === $a->reading_band);
                }

                return $a->group_tag && $l->schoolClass?->group_tag === $a->group_tag;
            });

            $reads = ReadingSession::whereIn('learner_id', $targets->pluck('id'))
                ->where('activity_id', $a->activity_id)
                ->where('session_type', '!=', 'Diagnostic')
                ->when($a->assigned_at, fn ($q) => $q->where('timestamp', '>=', $a->assigned_at))
                ->orderBy('timestamp')
                ->get()
                ->groupBy('learner_id')
                ->map(fn ($own) => $own->last());

            $for = match (true) {
                (bool) $a->learner_id => trim(($a->learner->first_name ?? '').' '.($a->learner->last_name ?? '')),
                (bool) $a->class_id => ($a->schoolClass->name ?? 'A class').($a->reading_band ? ' · '.strtolower(ReadingLevel::GROUPS[$a->reading_band]['title'] ?? $a->reading_band) : ''),
                default => 'Group '.$a->group_tag,
            };

            return [
                'activity' => $a->activity,
                'for' => $for,
                'total' => $targets->count(),
                'done' => $reads->count(),
                'avg' => $reads->isNotEmpty() ? (int) round($reads->avg('accuracy_percent')) : null,
            ];
        })->values();
    }

    /**
     * One row per learner in scope, for the class report a teacher can attach to the school's own
     * reading report. No learner code and no contact details: only what a report needs.
     *
     * @return list<list<string|int>>
     */
    public function reportRows(Teacher $teacher, Collection $classes, string $period): array
    {
        $learners = Learner::whereIn('class_id', $classes->pluck('id'))->with('schoolClass')->orderBy('last_name')->orderBy('first_name')->get();
        [$from, , $now] = $this->window($period);
        $sessions = $this->sessions($learners, $from, $now)->groupBy('learner_id');
        $lastRead = ReadingSession::whereIn('learner_id', $learners->pluck('id'))->where('session_type', '!=', 'Diagnostic')
            ->toBase()->selectRaw('learner_id, MAX(timestamp) as last_at')->groupBy('learner_id')->pluck('last_at', 'learner_id');
        $support = $this->alerts->forTeacher($teacher, withSuggestions: false)->where('kind', 'support')->pluck('learner.id')->flip();

        $rows = [];
        foreach ($learners as $learner) {
            $own = $sessions->get($learner->id, collect());
            $words = $own->flatMap(fn ($s) => collect((array) ($s->word_feedback ?? []))
                ->filter(fn ($w) => ErrorPatterns::classify((array) $w) !== null)
                ->pluck('reference')->filter()->map(fn ($w) => strtolower($w)))
                ->countBy()->sortDesc()->keys()->take(3)->implode(', ');
            $last = $lastRead[$learner->id] ?? null;

            $rows[] = [
                $learner->schoolClass->name ?? '',
                $learner->grade_level,
                trim($learner->first_name.' '.$learner->last_name),
                ReadingLevel::bandLabel(ReadingLevel::band($learner)),
                $own->count(),
                $own->isNotEmpty() ? (int) round($own->avg('accuracy_percent')) : '',
                $last ? LearnerClock::local($last)->format('Y-m-d') : '',
                $words,
                $support->has($learner->id) ? 'Yes' : 'No',
            ];
        }

        return $rows;
    }

    public static function reportHeader(): array
    {
        return ['Class', 'Grade', 'Learner', 'Reading level (Phil-IRI)', 'Readings', 'Average score %', 'Last reading', 'Words missed most', 'Needs support'];
    }

    /**
     * A spreadsheet treats a cell that starts with = + - or @ as a formula, which an attacker can
     * use through a learner's name or a word. Such a cell gets a leading apostrophe.
     */
    public static function csvSafe(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }

    /** @return Collection<int, ReadingSession> */
    private function sessions(Collection $learners, ?Carbon $from, Carbon $to): Collection
    {
        if ($learners->isEmpty()) {
            return collect();
        }

        return ReadingSession::whereIn('learner_id', $learners->pluck('id'))
            ->where('session_type', '!=', 'Diagnostic')
            ->when($from, fn ($q) => $q->where('timestamp', '>=', $from))
            ->where('timestamp', '<', $to)
            ->get();
    }
}
