<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

class LearnerController
{
    const AVATARS = ['lion', 'rabbit', 'fox', 'bear', 'panda', 'tiger', 'koala', 'frog'];

    private static function requireParent(): array
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
        if (!$user || $user['user_type'] !== 'Parent') {
            Response::error(403, 'Parent access required.');
        }
        if ($user['status'] === 'Inactive') {
            Response::error(403, 'This account has been deactivated.');
        }
        $stmt = $db->prepare('SELECT * FROM parents WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        return $stmt->fetch();
    }

    private static function generateLearnerCode(PDO $db): string
    {
        do {
            $code = 'TB-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $stmt = $db->prepare('SELECT id FROM learners WHERE learner_code = ?');
            $stmt->execute([$code]);
            $exists = $stmt->fetch();
        } while ($exists);
        return $code;
    }

    private static function masteryFromPlacement(int $yesCount): string
    {
        if ($yesCount <= 1) return 'Beginning';
        if ($yesCount === 2) return 'Developing';
        return 'Proficient'; // yesCount === 3
    }

    // ============================================================
    // POST /api/learner/create   (Parent only — the wizard)
    // Required: first_name, grade_level, pin (exactly 4 digits),
    //           placement_answers: [bool, bool, bool] (3 yes/no questions)
    // Optional: middle_name, last_name, learning_style
    // ============================================================
    public static function create(array $body): void
    {
        $parent = self::requireParent();

        if (empty($body['first_name']) || empty($body['grade_level']) || empty($body['pin'])) {
            Response::error(422, 'first_name, grade_level, and pin are required.');
        }
        if (!in_array($body['grade_level'], ['Grade 1', 'Grade 2', 'Grade 3'], true)) {
            Response::error(422, 'grade_level must be Grade 1, Grade 2, or Grade 3.');
        }
        if (!preg_match('/^\d{4}$/', (string) $body['pin'])) {
            Response::error(422, 'PIN must be exactly 4 digits.');
        }
        if (!isset($body['placement_answers']) || !is_array($body['placement_answers']) || count($body['placement_answers']) !== 3) {
            Response::error(422, 'placement_answers must be an array of exactly 3 yes/no (true/false) answers.');
        }
        if (isset($body['learning_style']) && !in_array($body['learning_style'], ['Visual', 'Listening', 'Hands-on'], true)) {
            Response::error(422, 'learning_style must be Visual, Listening, or Hands-on.');
        }

        $yesCount = count(array_filter($body['placement_answers']));
        $masteryLevel = self::masteryFromPlacement($yesCount);

        $db = Database::get();
        $learnerCode = self::generateLearnerCode($db);
        $avatar = self::AVATARS[array_rand(self::AVATARS)];

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO learners (learner_code, class_id, first_name, middle_name, last_name, grade_level, pin, avatar_id, mastery_level, learning_style, points, streak, status, created_at)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)'
            )->execute([
                $learnerCode,
                $body['first_name'],
                $body['middle_name'] ?? null,
                $body['last_name'] ?? '',
                $body['grade_level'],
                $body['pin'],
                $avatar,
                $masteryLevel,
                $body['learning_style'] ?? null,
                'Active',
                gmdate('Y-m-d H:i:s'),
            ]);
            $learnerId = (int) $db->lastInsertId();

            $db->prepare(
                'INSERT INTO parent_learners (parent_id, learner_id, relationship, is_creator, linked_at)
                 VALUES (?, ?, ?, 1, ?)'
            )->execute([$parent['id'], $learnerId, $body['relationship'] ?? 'Parent', gmdate('Y-m-d H:i:s')]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error(500, 'Could not create learner. Please try again.');
        }

        Response::json(201, [
            'message' => "Learner created. Estimated starting level: $masteryLevel.",
            'learner' => [
                'id' => $learnerId,
                'learner_code' => $learnerCode,
                'first_name' => $body['first_name'],
                'grade_level' => $body['grade_level'],
                'avatar_id' => $avatar,
                'mastery_level' => $masteryLevel,
                'class_id' => null,
            ],
        ]);
    }

    // ============================================================
    // GET /api/learner/my-learners   (Parent's linked children)
    // ============================================================
    public static function myLearners(): void
    {
        $parent = self::requireParent();
        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT l.* FROM learners l
             JOIN parent_learners pl ON pl.learner_id = l.id
             WHERE pl.parent_id = ?
             ORDER BY l.created_at ASC'
        );
        $stmt->execute([$parent['id']]);
        $learners = $stmt->fetchAll();

        $result = array_map(function ($l) {
            return [
                'id' => (int) $l['id'],
                'learner_code' => $l['learner_code'],
                'first_name' => $l['first_name'],
                'grade_level' => $l['grade_level'],
                'avatar_id' => $l['avatar_id'],
                'mastery_level' => $l['mastery_level'],
                'learning_style' => $l['learning_style'],
                'points' => (int) $l['points'],
                'streak' => (int) $l['streak'],
                'class_id' => $l['class_id'],
                'enrolled_in_class' => $l['class_id'] !== null,
            ];
        }, $learners);

        Response::json(200, ['learners' => $result]);
    }

    // ============================================================
    // POST /api/learner/link-parent   (second guardian linking)
    // Required: learner_code, relationship
    // ============================================================
    public static function linkParent(array $body): void
    {
        $parent = self::requireParent();

        if (empty($body['learner_code'])) {
            Response::error(422, 'learner_code is required.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM learners WHERE learner_code = ?');
        $stmt->execute([$body['learner_code']]);
        $learner = $stmt->fetch();
        if (!$learner) {
            Response::error(404, 'No learner found with that code.');
        }

        $stmt = $db->prepare('SELECT * FROM parent_learners WHERE parent_id = ? AND learner_id = ?');
        $stmt->execute([$parent['id'], $learner['id']]);
        if ($stmt->fetch()) {
            Response::error(409, 'This learner is already linked to your account.');
        }

        $db->prepare(
            'INSERT INTO parent_learners (parent_id, learner_id, relationship, is_creator, linked_at)
             VALUES (?, ?, ?, 0, ?)'
        )->execute([$parent['id'], $learner['id'], $body['relationship'] ?? 'Guardian', gmdate('Y-m-d H:i:s')]);

        Response::json(201, [
            'message' => 'Linked as a second guardian.',
            'learner' => [
                'id' => (int) $learner['id'],
                'first_name' => $learner['first_name'],
                'grade_level' => $learner['grade_level'],
                'learner_code' => $learner['learner_code'],
            ],
        ]);
    }
}
