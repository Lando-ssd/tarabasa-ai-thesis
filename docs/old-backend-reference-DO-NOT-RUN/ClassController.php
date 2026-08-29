<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

class ClassController
{
    private static function requireTeacher(): array
    {
        $token = Auth::bearerToken();
        if (!$token) {
            Response::error(401, 'No token provided.');
        }
        $tokenRow = Auth::resolveToken($token);
        if (!$tokenRow || $tokenRow['actor_type'] !== 'user') {
            Response::error(401, 'Invalid or expired token.');
        }
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$tokenRow['actor_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['user_type'] !== 'Teacher') {
            Response::error(403, 'Teacher access required.');
        }
        if ($user['status'] === 'Inactive') {
            Response::error(403, 'This account has been deactivated.');
        }
        $stmt = $db->prepare('SELECT * FROM teachers WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $teacher = $stmt->fetch();
        return ['user' => $user, 'teacher' => $teacher];
    }

    /**
     * Locked actions (create class, join learner) require Teacher.status === Active.
     * Pending teachers are rejected here with a clear message — the real security
     * boundary, regardless of what the frontend shows/hides.
     */
    private static function requireActiveTeacher(): array
    {
        $ctx = self::requireTeacher();
        if ($ctx['teacher']['status'] !== 'Active') {
            Response::error(403, 'Your account must be Active to do this. ' .
                ($ctx['teacher']['status'] === 'Pending'
                    ? 'Please wait for Admin approval.'
                    : 'Your registration was not approved.'));
        }
        return $ctx;
    }

    /**
     * Philippine school years run June–March/April. Computes the current
     * school year label (e.g. "2026-2027") from today's date server-side.
     */
    public static function currentSchoolYear(): string
    {
        $year = (int) gmdate('Y');
        $month = (int) gmdate('n');
        if ($month >= 6) {
            return "$year-" . ($year + 1);
        }
        return ($year - 1) . "-$year";
    }

    // ============================================================
    // POST /api/class/create   (requires Active teacher + school_year)
    // ============================================================
    public static function create(array $body): void
    {
        $ctx = self::requireActiveTeacher();

        $required = ['name', 'grade_level', 'section', 'school_year'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error(422, "Field '$field' is required.");
            }
        }
        if (!in_array($body['grade_level'], ['Grade 1', 'Grade 2', 'Grade 3'], true)) {
            Response::error(422, 'grade_level must be Grade 1, Grade 2, or Grade 3.');
        }

        $db = Database::get();
        $db->prepare(
            'INSERT INTO classes (teacher_id, name, grade_level, section, group_tag, school_year)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $ctx['teacher']['id'],
            $body['name'],
            $body['grade_level'],
            $body['section'],
            $body['group_tag'] ?? null,
            $body['school_year'],
        ]);
        $classId = (int) $db->lastInsertId();

        Response::json(201, [
            'class' => [
                'id' => $classId,
                'name' => $body['name'],
                'grade_level' => $body['grade_level'],
                'section' => $body['section'],
                'group_tag' => $body['group_tag'] ?? null,
                'school_year' => $body['school_year'],
            ],
        ]);
    }

    // ============================================================
    // GET /api/class/my-classes   (optional ?school_year=YYYY-YYYY filter)
    // Default (no filter): only the CURRENT school year's classes.
    // ============================================================
    public static function myClasses(array $query): void
    {
        $ctx = self::requireTeacher();
        $current = self::currentSchoolYear();
        $filterYear = $query['school_year'] ?? $current;

        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT * FROM classes WHERE teacher_id = ? AND school_year = ? ORDER BY name ASC'
        );
        $stmt->execute([$ctx['teacher']['id'], $filterYear]);
        $classes = $stmt->fetchAll();

        $result = array_map(function ($c) use ($current) {
            return [
                'id' => (int) $c['id'],
                'name' => $c['name'],
                'grade_level' => $c['grade_level'],
                'section' => $c['section'],
                'group_tag' => $c['group_tag'],
                'school_year' => $c['school_year'],
                'is_current_year' => $c['school_year'] === $current,
                'read_only' => $c['school_year'] !== $current,
            ];
        }, $classes);

        // Also return the list of every distinct school year this teacher has ever
        // had a class in, for the year-switcher dropdown.
        $stmt = $db->prepare(
            'SELECT DISTINCT school_year FROM classes WHERE teacher_id = ? ORDER BY school_year DESC'
        );
        $stmt->execute([$ctx['teacher']['id']]);
        $allYears = array_column($stmt->fetchAll(), 'school_year');

        Response::json(200, [
            'classes' => $result,
            'current_school_year' => $current,
            'available_school_years' => $allYears,
        ]);
    }

    // ============================================================
    // POST /api/learner/join-class   (Teacher only, requires Active)
    // ============================================================
    public static function joinClass(array $body): void
    {
        $ctx = self::requireActiveTeacher();

        if (empty($body['learner_code']) || empty($body['class_id'])) {
            Response::error(422, 'learner_code and class_id are required.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$body['class_id'], $ctx['teacher']['id']]);
        $class = $stmt->fetch();
        if (!$class) {
            Response::error(404, 'Class not found.');
        }
        if ($class['school_year'] !== self::currentSchoolYear()) {
            Response::error(403, 'Cannot add learners to a class from a past school year — it is read-only.');
        }

        $stmt = $db->prepare('SELECT * FROM learners WHERE learner_code = ?');
        $stmt->execute([$body['learner_code']]);
        $learner = $stmt->fetch();
        if (!$learner) {
            Response::error(404, 'No learner found with that code.');
        }
        if ($learner['class_id'] !== null) {
            Response::error(409, 'This learner is already in a class.');
        }

        $db->prepare('UPDATE learners SET class_id = ? WHERE id = ?')
            ->execute([$body['class_id'], $learner['id']]);

        Response::json(200, [
            'message' => 'Learner joined to class.',
            'learner_id' => (int) $learner['id'],
            'class_id' => (int) $body['class_id'],
        ]);
    }

    // ============================================================
    // GET /api/class/roster?class_id=X
    // Includes promotion/school-year history + proficiency trajectory
    // per the Learner History addition. NOTE: the class-name/teacher-name
    // enrichment for OLDER (non-most-recent) promotion steps is currently
    // limited — see the accompanying flag to the user about promotion_records
    // not storing historical class references. Grade-transition + date chain
    // is always accurate; class/teacher names are only shown for the CURRENT
    // class (live data), not reconstructed for prior steps.
    // ============================================================
    public static function roster(array $query): void
    {
        $ctx = self::requireTeacher();

        if (empty($query['class_id'])) {
            Response::error(422, 'class_id is required.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
        $stmt->execute([$query['class_id'], $ctx['teacher']['id']]);
        $class = $stmt->fetch();
        if (!$class) {
            Response::error(404, 'Class not found.');
        }

        $stmt = $db->prepare('SELECT * FROM learners WHERE class_id = ? ORDER BY first_name ASC');
        $stmt->execute([$query['class_id']]);
        $learners = $stmt->fetchAll();

        $roster = [];
        foreach ($learners as $l) {
            $roster[] = [
                'id' => (int) $l['id'],
                'first_name' => $l['first_name'],
                'learner_code' => $l['learner_code'],
                'mastery_level' => $l['mastery_level'],
                'promotion_history' => self::promotionHistory($db, (int) $l['id']),
                'proficiency_trajectory' => self::proficiencyTrajectory($db, (int) $l['id']),
            ];
        }

        Response::json(200, [
            'class' => [
                'id' => (int) $class['id'],
                'name' => $class['name'],
                'grade_level' => $class['grade_level'],
                'section' => $class['section'],
                'school_year' => $class['school_year'],
            ],
            'roster' => $roster,
        ]);
    }

    /**
     * Looks up a class + its owning teacher's display name, for building a
     * rich chain entry like "Grade 1 - CCS (SY 2025-2026, Teacher: Jenny Reyes)".
     * Returns null if the class_id is null (e.g. an older record created before
     * this patch, or a Pending record with no claimed_into_class_id yet).
     */
    private static function classLabel(PDO $db, ?int $classId): ?array
    {
        if ($classId === null) {
            return null;
        }
        $stmt = $db->prepare(
            'SELECT c.name, c.grade_level, c.section, c.school_year,
                    u.first_name, u.last_name
             FROM classes c
             JOIN teachers t ON t.id = c.teacher_id
             JOIN users u ON u.id = t.user_id
             WHERE c.id = ?'
        );
        $stmt->execute([$classId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'grade_level' => $row['grade_level'],
            'class_name' => $row['name'],
            'section' => $row['section'],
            'school_year' => $row['school_year'],
            'teacher_name' => "{$row['first_name']} {$row['last_name']}",
            'label' => "{$row['grade_level']} - {$row['name']} (SY {$row['school_year']}, Teacher: {$row['first_name']} {$row['last_name']})",
        ];
    }

    private static function promotionHistory(PDO $db, int $learnerId): array
    {
        $stmt = $db->prepare(
            "SELECT * FROM promotion_records WHERE learner_id = ? ORDER BY released_at ASC"
        );
        $stmt->execute([$learnerId]);
        $records = $stmt->fetchAll();

        $claimed = array_values(array_filter($records, fn($r) => $r['status'] === 'Claimed'));

        if (empty($claimed)) {
            return [
                'headline' => 'New to the system — no prior grade history.',
                'chain' => [],
            ];
        }

        $mostRecent = end($claimed);
        $headline = "Promoted from a previous class — now in {$mostRecent['next_grade']} since " .
            date('F Y', strtotime($mostRecent['claimed_at']));

        $chain = [];
        foreach ($claimed as $r) {
            $fromClass = self::classLabel($db, $r['released_from_class_id'] ?? null);
            $toClass = self::classLabel($db, $r['claimed_into_class_id'] ?? null);

            // Fallback to grade-number inference only if the rich class link is
            // missing on this particular record (e.g. legacy data) — still never
            // breaks, just degrades gracefully to the simpler line for that step.
            $prevGradeNum = (int) filter_var($r['next_grade'], FILTER_SANITIZE_NUMBER_INT) - 1;
            $prevGrade = "Grade $prevGradeNum";

            $chain[] = [
                'from_grade' => $prevGrade,
                'to_grade' => $r['next_grade'],
                'claimed_at' => $r['claimed_at'],
                'from_class' => $fromClass, // null if not recoverable
                'to_class' => $toClass,     // null if not recoverable
                'display' => ($fromClass && $toClass)
                    ? "{$fromClass['label']} → {$toClass['label']}"
                    : "$prevGrade → {$r['next_grade']} (claimed " . date('F Y', strtotime($r['claimed_at'])) . ')',
            ];
        }

        return ['headline' => $headline, 'chain' => $chain];
    }

    private static function proficiencyTrajectory(PDO $db, int $learnerId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM reading_sessions WHERE learner_id = ? ORDER BY timestamp ASC'
        );
        $stmt->execute([$learnerId]);
        $sessions = $stmt->fetchAll();

        if (empty($sessions)) {
            $stmt = $db->prepare('SELECT mastery_level FROM learners WHERE id = ?');
            $stmt->execute([$learnerId]);
            $level = $stmt->fetch()['mastery_level'] ?? 'Beginning';
            return [
                'text' => "No reading sessions recorded yet. Starting level: $level",
                'started_at' => null,
                'currently_at' => null,
            ];
        }

        $first = $sessions[0];
        $latest = end($sessions);
        return [
            'text' => "Proficiency: started at {$first['level_before']} -> currently {$latest['level_after']}",
            'started_at' => $first['level_before'],
            'currently_at' => $latest['level_after'],
        ];
    }
}
