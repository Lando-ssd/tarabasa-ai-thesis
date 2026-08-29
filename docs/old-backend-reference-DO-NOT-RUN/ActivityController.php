<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/ContentGenerator.php';

class ActivityController
{
    const VALID_GAME_TYPES = [
        'Read Aloud', 'Letter-Sound Match', 'Word Builder',
        'Trace-and-Write', 'Sentence Scramble', 'Picture-Word Match',
    ];

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

    // ============================================================
    // POST /api/activity/generate
    // Body: topic, grade_level, skill_focus (optional), game_type,
    //       target_learner_id (optional)
    // ============================================================
    public static function generate(array $body): void
    {
        $ctx = self::requireTeacher();
        $teacher = $ctx['teacher'];

        if (empty($body['topic']) || empty($body['grade_level']) || empty($body['game_type'])) {
            Response::error(422, 'topic, grade_level, and game_type are required.');
        }
        if (!in_array($body['game_type'], self::VALID_GAME_TYPES, true)) {
            Response::error(422, 'game_type must be one of: ' . implode(', ', self::VALID_GAME_TYPES));
        }
        if (!in_array($body['grade_level'], ['Grade 1', 'Grade 2', 'Grade 3'], true)) {
            Response::error(422, 'grade_level must be Grade 1, Grade 2, or Grade 3.');
        }

        // Credit gate — applies IDENTICALLY whether Pending or Active (Activity
        // Content Addition, Part 1). The only difference is the guidance message,
        // since a Pending teacher literally cannot reach My Activities/Share yet.
        if ($teacher['free_generation_credits_remaining'] <= 0) {
            $howToGetMore = $teacher['status'] === 'Pending'
                ? 'Wait for Admin approval, then share an approved activity to earn more.'
                : 'Share an approved activity to the Open Repository to earn 2 more credits.';
            Response::error(403, "No generation credits remaining. $howToGetMore");
        }

        $db = Database::get();

        // Curriculum Guide matching — read-only lookup, traceability shown on the Draft.
        $stmt = $db->prepare(
            'SELECT * FROM curriculum_guides WHERE grade_level = ? AND LOWER(topic) LIKE LOWER(?) LIMIT 1'
        );
        $stmt->execute([$body['grade_level'], '%' . $body['topic'] . '%']);
        $matchedGuide = $stmt->fetch();

        $sampleVocabulary = null;
        if ($matchedGuide && $matchedGuide['sample_vocabulary']) {
            $sampleVocabulary = json_decode($matchedGuide['sample_vocabulary'], true);
        }

        // Adaptivity — 1-3 of the target Learner's Struggling words woven in,
        // skipped silently if none exist yet (Part 5).
        $strugglingWords = [];
        $targetLearner = null;
        if (!empty($body['target_learner_id'])) {
            $stmt = $db->prepare('SELECT * FROM learners WHERE id = ?');
            $stmt->execute([$body['target_learner_id']]);
            $targetLearner = $stmt->fetch();
            if (!$targetLearner) {
                Response::error(404, 'Target learner not found.');
            }
            $stmt = $db->prepare(
                "SELECT DISTINCT word FROM personal_word_bank WHERE learner_id = ? AND mastery_status = 'Struggling' LIMIT 3"
            );
            $stmt->execute([$body['target_learner_id']]);
            $strugglingWords = array_column($stmt->fetchAll(), 'word');
        }

        // difficultyTier: from target Learner's masteryLevel if selected, else
        // default Medium. The frontend's standalone "Target Mastery Level"
        // dropdown is intentionally NOT read here (confirmed conflict #6) —
        // this is always system-derived, never manually typed.
        $difficultyTier = 'Medium';
        if ($targetLearner && $targetLearner['mastery_level']) {
            $difficultyTier = match ($targetLearner['mastery_level']) {
                'Beginning' => 'Easy',
                'Developing' => 'Medium',
                'Proficient' => 'Hard',
                default => 'Medium',
            };
        }

        $generated = ContentGenerator::generate(
            $body['game_type'],
            $body['grade_level'],
            $body['topic'],
            $body['skill_focus'] ?? null,
            $sampleVocabulary,
            $strugglingWords
        );

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO activities
                 (created_by_teacher_id, curriculum_guide_id, topic, grade_level, skill_focus, game_type,
                  difficulty_tier, status, shared_to_repository, passage_text, content, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
            )->execute([
                $teacher['id'],
                $matchedGuide['id'] ?? null,
                $body['topic'],
                $body['grade_level'],
                $body['skill_focus'] ?? null,
                $body['game_type'],
                $difficultyTier,
                'Draft',
                $generated['passage_text'],
                json_encode($generated['content']),
                gmdate('Y-m-d H:i:s'),
            ]);
            $activityId = (int) $db->lastInsertId();

            $db->prepare('UPDATE teachers SET free_generation_credits_remaining = free_generation_credits_remaining - 1 WHERE id = ?')
                ->execute([$teacher['id']]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error(500, 'Generation failed. Please try again. (' . $e->getMessage() . ')');
        }

        $stmt = $db->prepare('SELECT free_generation_credits_remaining FROM teachers WHERE id = ?');
        $stmt->execute([$teacher['id']]);
        $remaining = (int) $stmt->fetch()['free_generation_credits_remaining'];

        Response::json(201, [
            'activity' => [
                'id' => $activityId,
                'topic' => $body['topic'],
                'grade_level' => $body['grade_level'],
                'skill_focus' => $body['skill_focus'] ?? null,
                'game_type' => $body['game_type'],
                'difficulty_tier' => $difficultyTier,
                'status' => 'Draft',
                'passage_text' => $generated['passage_text'],
                'content' => $generated['content'],
                'curriculum_match' => $matchedGuide ? [
                    'matched' => true,
                    'curriculum_guide_id' => (int) $matchedGuide['id'],
                    'matched_topic' => $matchedGuide['topic'],
                ] : ['matched' => false],
            ],
            'free_generation_credits_remaining' => $remaining,
        ]);
    }

    // ============================================================
    // GET /api/activity/my-activities
    // ============================================================
    public static function myActivities(): void
    {
        $ctx = self::requireTeacher();
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM activities WHERE created_by_teacher_id = ? ORDER BY created_at DESC');
        $stmt->execute([$ctx['teacher']['id']]);
        $activities = $stmt->fetchAll();

        $result = array_map(function ($a) use ($db) {
            $rating = null;
            if ($a['shared_to_repository']) {
                $stmt = $db->prepare('SELECT id FROM open_repository_listings WHERE activity_id = ?');
                $stmt->execute([$a['id']]);
                $listing = $stmt->fetch();
                if ($listing) {
                    $stmt = $db->prepare(
                        'SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM repository_ratings WHERE listing_id = ?'
                    );
                    $stmt->execute([$listing['id']]);
                    $r = $stmt->fetch();
                    $rating = [
                        'average' => $r['avg_rating'] !== null ? round((float) $r['avg_rating'], 1) : null,
                        'count' => (int) $r['cnt'],
                    ];
                }
            }
            return [
                'id' => (int) $a['id'],
                'topic' => $a['topic'],
                'grade_level' => $a['grade_level'],
                'skill_focus' => $a['skill_focus'],
                'game_type' => $a['game_type'],
                'difficulty_tier' => $a['difficulty_tier'],
                'status' => $a['status'],
                'passage_text' => $a['passage_text'],
                'content' => json_decode($a['content'], true),
                'shared_to_repository' => (bool) $a['shared_to_repository'],
                'rating' => $rating,
            ];
        }, $activities);

        Response::json(200, ['activities' => $result]);
    }

    // ============================================================
    // POST /api/activity/decision
    // Body: activity_id, decision: Approve|Edit|Reject, content?, passage_text?
    // ============================================================
    public static function decision(array $body): void
    {
        $ctx = self::requireTeacher();

        if (empty($body['activity_id']) || empty($body['decision'])) {
            Response::error(422, 'activity_id and decision are required.');
        }
        if (!in_array($body['decision'], ['Approve', 'Edit', 'Reject'], true)) {
            Response::error(422, 'decision must be Approve, Edit, or Reject.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM activities WHERE id = ? AND created_by_teacher_id = ?');
        $stmt->execute([$body['activity_id'], $ctx['teacher']['id']]);
        $activity = $stmt->fetch();
        if (!$activity) {
            Response::error(404, 'Activity not found.');
        }
        if ($activity['status'] !== 'Draft') {
            Response::error(409, 'Only Draft activities awaiting review can be decided on.');
        }

        $warning = null;

        if ($body['decision'] === 'Reject') {
            $db->prepare("UPDATE activities SET status = 'Rejected' WHERE id = ?")->execute([$activity['id']]);
            Response::json(200, ['message' => 'Activity rejected.', 'activity_id' => (int) $activity['id'], 'status' => 'Rejected']);
        }

        // Approve and Edit both land on Approved — editing and approving are one
        // combined action, never two separate steps (Part 6).
        $passageText = $activity['passage_text'];
        $content = json_decode($activity['content'], true);

        if ($body['decision'] === 'Edit') {
            if (isset($body['passage_text'])) {
                $passageText = $body['passage_text'];
            }
            if (isset($body['content']) && is_array($body['content'])) {
                $content = $body['content'];
                // Soft warning only — never a hard block — if a list-based field
                // drops below the practical minimum of 5 items (edge case rule).
                foreach (['letterSoundPairs', 'targetWords', 'traceWords', 'scrambledSentences', 'wordImagePairs'] as $listField) {
                    if (isset($content[$listField]) && is_array($content[$listField]) && count($content[$listField]) < 5) {
                        $warning = "Note: '$listField' has fewer than 5 items. This is allowed, but real activities are usually stronger with at least 5.";
                    }
                }
            }
        }

        $db->prepare("UPDATE activities SET status = 'Approved', passage_text = ?, content = ? WHERE id = ?")
            ->execute([$passageText, json_encode($content), $activity['id']]);

        Response::json(200, array_filter([
            'message' => 'Activity approved.',
            'activity_id' => (int) $activity['id'],
            'status' => 'Approved',
            'warning' => $warning,
        ], fn($v) => $v !== null));
    }

    // ============================================================
    // POST /api/activity/assign
    // Body: activity_id, and EXACTLY ONE of learner_id / class_id / group_tag
    // ============================================================
    public static function assign(array $body): void
    {
        $ctx = self::requireTeacher();

        if (empty($body['activity_id'])) {
            Response::error(422, 'activity_id is required.');
        }

        $targets = array_filter([
            'learner_id' => $body['learner_id'] ?? null,
            'class_id' => $body['class_id'] ?? null,
            'group_tag' => $body['group_tag'] ?? null,
        ]);
        if (count($targets) !== 1) {
            Response::error(422, 'Choose exactly one of learner_id, class_id, or group_tag.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM activities WHERE id = ? AND created_by_teacher_id = ?');
        $stmt->execute([$body['activity_id'], $ctx['teacher']['id']]);
        $activity = $stmt->fetch();
        if (!$activity) {
            Response::error(404, 'Activity not found.');
        }
        if ($activity['status'] !== 'Approved') {
            Response::error(422, 'Only Approved activities can be assigned.');
        }

        $learnerId = null;
        $classId = null;
        $groupTag = null;

        if (isset($targets['learner_id'])) {
            $stmt = $db->prepare(
                'SELECT l.* FROM learners l JOIN classes c ON c.id = l.class_id
                 WHERE l.id = ? AND c.teacher_id = ?'
            );
            $stmt->execute([$body['learner_id'], $ctx['teacher']['id']]);
            $learner = $stmt->fetch();
            if (!$learner) {
                Response::error(404, 'Learner not found in your roster.');
            }
            $learnerId = (int) $learner['id'];
        } elseif (isset($targets['class_id'])) {
            $stmt = $db->prepare('SELECT * FROM classes WHERE id = ? AND teacher_id = ?');
            $stmt->execute([$body['class_id'], $ctx['teacher']['id']]);
            $class = $stmt->fetch();
            if (!$class) {
                Response::error(404, 'Class not found.');
            }
            $classId = (int) $class['id'];
        } else {
            $stmt = $db->prepare('SELECT id FROM classes WHERE teacher_id = ? AND group_tag = ? LIMIT 1');
            $stmt->execute([$ctx['teacher']['id'], $body['group_tag']]);
            if (!$stmt->fetch()) {
                Response::error(404, 'No class with that group tag found among your classes.');
            }
            $groupTag = $body['group_tag'];
        }

        $db->prepare(
            'INSERT INTO activity_assignments (activity_id, learner_id, class_id, group_tag, assigned_by_teacher_id, assigned_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$activity['id'], $learnerId, $classId, $groupTag, $ctx['teacher']['id'], gmdate('Y-m-d H:i:s')]);

        Response::json(201, [
            'message' => 'Activity assigned.',
            'activity_id' => (int) $activity['id'],
            'target' => $learnerId ? ['learner_id' => $learnerId] : ($classId ? ['class_id' => $classId] : ['group_tag' => $groupTag]),
        ]);
    }

    // ============================================================
    // POST /api/activity/share
    // Body: activity_id, price_type: Free|Paid, price? (required if Paid)
    // ============================================================
    public static function share(array $body): void
    {
        $ctx = self::requireTeacher();

        if (empty($body['activity_id']) || empty($body['price_type'])) {
            Response::error(422, 'activity_id and price_type are required.');
        }
        if (!in_array($body['price_type'], ['Free', 'Paid'], true)) {
            Response::error(422, 'price_type must be Free or Paid.');
        }
        if ($body['price_type'] === 'Paid' && (empty($body['price']) || $body['price'] <= 0)) {
            Response::error(422, 'A positive price is required for a Paid listing.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM activities WHERE id = ? AND created_by_teacher_id = ?');
        $stmt->execute([$body['activity_id'], $ctx['teacher']['id']]);
        $activity = $stmt->fetch();
        if (!$activity) {
            Response::error(404, 'Activity not found.');
        }
        if ($activity['status'] !== 'Approved') {
            Response::error(422, 'Only Approved activities can be shared.');
        }
        if ($activity['shared_to_repository']) {
            Response::error(409, 'This activity has already been shared to the Open Repository.');
        }

        $price = $body['price_type'] === 'Paid' ? (float) $body['price'] : 0;

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO open_repository_listings (activity_id, teacher_id, price_type, price, listed_at)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$activity['id'], $ctx['teacher']['id'], $body['price_type'], $price, gmdate('Y-m-d H:i:s')]);
            $listingId = (int) $db->lastInsertId();

            $db->prepare('UPDATE activities SET shared_to_repository = 1 WHERE id = ?')->execute([$activity['id']]);

            // +2 credits regardless of Free/Paid, regardless of Pending/Active —
            // no separate rule for either status (Activity Content Addition, Part 1).
            $db->prepare('UPDATE teachers SET free_generation_credits_remaining = free_generation_credits_remaining + 2 WHERE id = ?')
                ->execute([$ctx['teacher']['id']]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error(500, 'Sharing failed. Please try again.');
        }

        $stmt = $db->prepare('SELECT free_generation_credits_remaining FROM teachers WHERE id = ?');
        $stmt->execute([$ctx['teacher']['id']]);
        $remaining = (int) $stmt->fetch()['free_generation_credits_remaining'];

        Response::json(201, [
            'message' => '✓ Shared to Repository.',
            'listing_id' => $listingId,
            'free_generation_credits_remaining' => $remaining,
        ]);
    }
}
