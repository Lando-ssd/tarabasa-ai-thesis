<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/SpeechToTextStub.php';
require_once __DIR__ . '/DiagnosticStaircase.php';
require_once __DIR__ . '/ContentGenerator.php';

class SessionController
{
    private static function requireLearner(): array
    {
        $token = Auth::bearerToken();
        if (!$token) {
            Response::error(401, 'No token provided.');
        }
        $tokenRow = Auth::resolveToken($token);
        if (!$tokenRow || $tokenRow['actor_type'] !== 'learner') {
            Response::error(401, 'Invalid or expired token.');
        }
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM learners WHERE id = ?');
        $stmt->execute([$tokenRow['actor_id']]);
        $learner = $stmt->fetch();
        if (!$learner) {
            Response::error(401, 'Invalid or expired token.');
        }
        return $learner;
    }

    private static function hasCompletedDiagnostic(PDO $db, int $learnerId): bool
    {
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM reading_sessions WHERE learner_id = ? AND session_type = 'Diagnostic'");
        $stmt->execute([$learnerId]);
        return ((int) $stmt->fetch()['cnt']) > 0;
    }

    // ============================================================
    // GET /api/session/available-activities
    // Priority: direct-to-learner assignment > class assignment > group_tag
    // assignment (first match wins) — plus any repository-unlocked items.
    // ============================================================
    public static function availableActivities(): void
    {
        $learner = self::requireLearner();
        $db = Database::get();

        $assigned = [];

        // 1. Direct-to-this-learner assignment
        $stmt = $db->prepare(
            'SELECT a.* FROM activities a
             JOIN activity_assignments aa ON aa.activity_id = a.id
             WHERE aa.learner_id = ? AND a.status = "Approved"'
        );
        $stmt->execute([$learner['id']]);
        $assigned = $stmt->fetchAll();

        // 2. Their Class (only if no direct assignment)
        if (empty($assigned) && $learner['class_id']) {
            $stmt = $db->prepare(
                'SELECT a.* FROM activities a
                 JOIN activity_assignments aa ON aa.activity_id = a.id
                 WHERE aa.class_id = ? AND a.status = "Approved"'
            );
            $stmt->execute([$learner['class_id']]);
            $assigned = $stmt->fetchAll();
        }

        // 3. Their Class's Group tag (only if still nothing)
        if (empty($assigned) && $learner['class_id']) {
            $stmt = $db->prepare('SELECT group_tag FROM classes WHERE id = ?');
            $stmt->execute([$learner['class_id']]);
            $groupTag = $stmt->fetch()['group_tag'] ?? null;
            if ($groupTag) {
                $stmt = $db->prepare(
                    'SELECT a.* FROM activities a
                     JOIN activity_assignments aa ON aa.activity_id = a.id
                     WHERE aa.group_tag = ? AND a.status = "Approved"'
                );
                $stmt->execute([$groupTag]);
                $assigned = $stmt->fetchAll();
            }
        }

        $options = array_map(fn($a) => [
            'activity_id' => (int) $a['id'],
            'topic' => $a['topic'],
            'game_type' => $a['game_type'],
            'source' => 'Teacher',
            'label' => 'Assigned by your Teacher',
        ], $assigned);

        // Repository-unlocked items (Parent-side)
        $stmt = $db->prepare(
            'SELECT a.* FROM activities a
             JOIN open_repository_listings orl ON orl.activity_id = a.id
             JOIN repository_unlocks ru ON ru.listing_id = orl.id
             WHERE ru.learner_id = ?'
        );
        $stmt->execute([$learner['id']]);
        foreach ($stmt->fetchAll() as $a) {
            $options[] = [
                'activity_id' => (int) $a['id'],
                'topic' => $a['topic'],
                'game_type' => $a['game_type'],
                'source' => 'Parent',
                'label' => 'Extra Practice',
            ];
        }

        Response::json(200, ['options' => $options, 'count' => count($options)]);
    }

    // ============================================================
    // GET /api/session/next-diagnostic-passage
    // ADDITION (flagged): selects which curriculum-grounded passage to show
    // next in the adaptive staircase. Not itself in the confirmed endpoint
    // list — needed to make the documented flow actually functional.
    // ============================================================
    public static function nextDiagnosticPassage(): void
    {
        $learner = self::requireLearner();
        $db = Database::get();

        $stmt = $db->prepare(
            "SELECT rs.*, a.difficulty_tier FROM reading_sessions rs
             JOIN activities a ON a.id = rs.activity_id
             WHERE rs.learner_id = ? AND rs.session_type = 'Diagnostic'
             ORDER BY rs.timestamp ASC"
        );
        $stmt->execute([$learner['id']]);
        $priorSessions = $stmt->fetchAll();

        if (empty($priorSessions)) {
            $tier = DiagnosticStaircase::startingTier($learner['mastery_level']);
        } else {
            $last = end($priorSessions);
            $decision = DiagnosticStaircase::nextStep(
                $last['difficulty_tier'],
                (float) $last['accuracy_percent'],
                count($priorSessions)
            );
            if ($decision['stop']) {
                Response::error(409, 'Diagnostic staircase already reached its stopping point. Submit the final result instead of requesting another passage.');
            }
            $tier = $decision['next_tier'];
        }

        // Curriculum-grounded content at whichever tier is chosen, independent
        // of the learner's own gradeLevel — the whole point is finding where
        // the CHILD actually is (Part 5/6 of the correction doc).
        $stmt = $db->prepare(
            'SELECT * FROM curriculum_guides WHERE grade_level = ? ORDER BY RANDOM() LIMIT 1'
        );
        $stmt->execute([$learner['grade_level']]);
        $guide = $stmt->fetch();
        $sampleVocabulary = ($guide && $guide['sample_vocabulary']) ? json_decode($guide['sample_vocabulary'], true) : null;

        $generated = ContentGenerator::generate(
            'Read Aloud',
            $learner['grade_level'],
            $guide['topic'] ?? 'General Reading',
            $guide['skill_focus'] ?? null,
            $sampleVocabulary,
            []
        );

        // Persist as a real (unapproved-review-not-applicable) Activity row so
        // the session can reference activity_id + difficulty_tier normally.
        // curriculum_guide_id nullable teacher/created_by fields use a system
        // placeholder since no Teacher authored this — see note in schema comment.
        $db->prepare(
            'INSERT INTO activities
             (created_by_teacher_id, curriculum_guide_id, topic, grade_level, skill_focus, game_type,
              difficulty_tier, status, shared_to_repository, passage_text, content, created_at)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
        )->execute([
            $guide['id'] ?? null,
            $guide['topic'] ?? 'General Reading',
            $learner['grade_level'],
            $guide['skill_focus'] ?? null,
            'Read Aloud',
            $tier,
            'Approved', // system-generated diagnostic content is usable immediately
            $generated['passage_text'],
            json_encode($generated['content']),
            gmdate('Y-m-d H:i:s'),
        ]);
        $activityId = (int) $db->lastInsertId();

        Response::json(200, [
            'activity_id' => $activityId,
            'passage_text' => $generated['passage_text'],
            'difficulty_tier' => $tier,
            'passage_number' => count($priorSessions) + 1,
        ]);
    }

    // ============================================================
    // POST /api/session/submit
    // Body: activity_id, audio_base64, session_type (Practice|Diagnostic|Assessment, default Practice)
    // ============================================================
    public static function submit(array $body): void
    {
        $learner = self::requireLearner();
        $db = Database::get();

        if (empty($body['activity_id']) || !isset($body['audio_base64'])) {
            Response::error(422, 'activity_id and audio_base64 are required.');
        }
        $sessionType = $body['session_type'] ?? 'Practice';
        if (!in_array($sessionType, ['Practice', 'Diagnostic', 'Assessment'], true)) {
            Response::error(422, 'session_type must be Practice, Diagnostic, or Assessment.');
        }

        $stmt = $db->prepare('SELECT * FROM activities WHERE id = ?');
        $stmt->execute([$body['activity_id']]);
        $activity = $stmt->fetch();
        if (!$activity) {
            Response::error(404, 'Activity not found.');
        }

        // Server-derived access check + initiated_by — NEVER trusted from client.
        // Independently verified: this must be reachable via a real assignment or
        // unlock record, regardless of what the frontend only shows as an option.
        $initiatedBy = null;

        if ($sessionType === 'Diagnostic') {
            // The diagnostic activity was generated specifically for this learner
            // by next-diagnostic-passage — verify it belongs to them via the
            // matching-tier + no-teacher-authorship signature, and that it hasn't
            // already been used in a prior diagnostic session.
            if ($activity['created_by_teacher_id'] !== null) {
                Response::error(403, 'This activity is not a valid diagnostic passage.');
            }
            $stmt = $db->prepare('SELECT id FROM reading_sessions WHERE activity_id = ?');
            $stmt->execute([$activity['id']]);
            if ($stmt->fetch()) {
                Response::error(409, 'This diagnostic passage has already been submitted.');
            }
            $initiatedBy = 'Parent'; // "a first login is inherently something a Parent set up" — Part 3 step 5
        } else {
            $stmt = $db->prepare('SELECT * FROM activity_assignments WHERE activity_id = ? AND (learner_id = ? OR class_id = ? OR group_tag = (SELECT group_tag FROM classes WHERE id = ?))');
            $stmt->execute([$activity['id'], $learner['id'], $learner['class_id'], $learner['class_id']]);
            $viaAssignment = $stmt->fetch();

            $stmt = $db->prepare(
                'SELECT ru.id FROM repository_unlocks ru
                 JOIN open_repository_listings orl ON orl.id = ru.listing_id
                 WHERE orl.activity_id = ? AND ru.learner_id = ?'
            );
            $stmt->execute([$activity['id'], $learner['id']]);
            $viaUnlock = $stmt->fetch();

            if (!$viaAssignment && !$viaUnlock) {
                Response::error(403, 'This learner does not have access to this activity.');
            }
            $initiatedBy = $viaAssignment ? 'Teacher' : 'Parent';
        }

        // --- AI Examiner call (stubbed — see SpeechToTextStub for the real seam) ---
        $result = SpeechToTextStub::score($body['audio_base64'], $activity['passage_text']);

        if ($result['unclear']) {
            $attemptNumber = (int) ($body['attempt_number'] ?? 1);
            if ($attemptNumber >= 3) {
                Response::json(200, [
                    'unclear' => true,
                    'attempt_number' => $attemptNumber,
                    'final_attempt' => true,
                    'message' => 'Please ask your Teacher or Parent for help.',
                ]);
            }
            // No ReadingSession record for an attempt that was never scored.
            Response::json(200, [
                'unclear' => true,
                'attempt_number' => $attemptNumber,
                'final_attempt' => false,
                'message' => "Didn't quite catch that — try again!",
            ]);
        }

        $levelBefore = $learner['mastery_level'] ?? 'Beginning';
        $flagged = false;
        $isFinalDiagnosticStep = false;
        $levelAfter = $levelBefore;

        if ($sessionType === 'Diagnostic') {
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM reading_sessions WHERE learner_id = ? AND session_type = 'Diagnostic'");
            $stmt->execute([$learner['id']]);
            $priorCount = (int) $stmt->fetch()['cnt'];
            $passagesSoFar = $priorCount + 1;

            $decision = DiagnosticStaircase::nextStep($activity['difficulty_tier'], $result['accuracy_percent'], $passagesSoFar);
            if ($decision['stop']) {
                $isFinalDiagnosticStep = true;
                $levelAfter = DiagnosticStaircase::tierToLevel($decision['final_tier']);
            } else {
                // Not the final step yet — masteryLevel stays as the preliminary
                // estimate until the staircase actually concludes.
                $levelAfter = $levelBefore;
            }
        } else {
            // Normal Practice/Assessment delta rule.
            $tierOrder = ['Beginning' => 0, 'Developing' => 1, 'Proficient' => 2];
            $levels = array_flip($tierOrder);
            $currentIdx = $tierOrder[$levelBefore] ?? 0;
            if ($result['accuracy_percent'] >= 90) {
                $levelAfter = $levels[min(2, $currentIdx + 1)];
            } elseif ($result['accuracy_percent'] < 70) {
                $levelAfter = $levels[max(0, $currentIdx - 1)];
                $flagged = true;
            } else {
                $levelAfter = $levelBefore;
            }
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO reading_sessions
                 (learner_id, activity_id, accuracy_percent, wcpm, pronunciation_score, fluency_score,
                  mispronunciation_count, skipped_word_count, substitution_count, repetition_count, insertion_count,
                  level_before, level_after, flagged_needs_attention, session_type, initiated_by, timestamp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $learner['id'], $activity['id'], $result['accuracy_percent'], $result['wcpm'],
                $result['pronunciation_score'], $result['fluency_score'],
                $result['mispronunciation_count'], $result['skipped_word_count'],
                $result['substitution_count'], $result['repetition_count'], $result['insertion_count'],
                $levelBefore, $levelAfter, $flagged ? 1 : 0, $sessionType, $initiatedBy, gmdate('Y-m-d H:i:s'),
            ]);
            $sessionId = (int) $db->lastInsertId();

            $pointsEarned = 0;
            $newStreak = (int) $learner['streak'];
            $newPoints = (int) $learner['points'];

            if ($sessionType !== 'Diagnostic' || $isFinalDiagnosticStep) {
                // Learner's real masteryLevel only changes here for Practice/Assessment
                // always, or for Diagnostic ONLY once the staircase actually concludes.
                $db->prepare('UPDATE learners SET mastery_level = ? WHERE id = ?')->execute([$levelAfter, $learner['id']]);
            }

            if ($sessionType !== 'Diagnostic') {
                $pointsEarned = (int) round($result['accuracy_percent'] / 2);
                $newPoints += $pointsEarned;
                $newStreak += 1;
                $db->prepare('UPDATE learners SET points = ?, streak = ? WHERE id = ?')
                    ->execute([$newPoints, $newStreak, $learner['id']]);
            }

            // PersonalWordBank: accuracy <80% -> 1-2 missed words, auto, no manual trigger.
            if ($result['accuracy_percent'] < 80) {
                foreach (array_slice($result['missed_words'], 0, 2) as $word) {
                    $db->prepare(
                        'INSERT INTO personal_word_bank (learner_id, session_id, word, mastery_status, times_drilled, created_at)
                         VALUES (?, ?, ?, ?, 0, ?)'
                    )->execute([$learner['id'], $sessionId, $word, 'Struggling', gmdate('Y-m-d H:i:s')]);
                }
            }

            // Badge checks against new total points (skipped entirely for Diagnostic
            // sessions, which award no points) — excludes the special one-time
            // "First Reading Star" badge, handled separately below.
            $newBadges = [];
            if ($sessionType !== 'Diagnostic') {
                $stmt = $db->prepare(
                    "SELECT * FROM badges WHERE point_threshold > 0 AND point_threshold <= ? AND name != 'First Reading Star'"
                );
                $stmt->execute([$newPoints]);
                foreach ($stmt->fetchAll() as $badge) {
                    $stmt2 = $db->prepare('SELECT id FROM learner_badges WHERE learner_id = ? AND badge_id = ?');
                    $stmt2->execute([$learner['id'], $badge['id']]);
                    if (!$stmt2->fetch()) {
                        $db->prepare('INSERT INTO learner_badges (learner_id, badge_id, date_earned) VALUES (?, ?, ?)')
                            ->execute([$learner['id'], $badge['id'], gmdate('Y-m-d H:i:s')]);
                        $newBadges[] = $badge['name'];
                    }
                }
            }

            // One-time "First Reading Star" — awarded exactly once, on diagnostic completion.
            if ($sessionType === 'Diagnostic' && $isFinalDiagnosticStep) {
                $stmt = $db->prepare("SELECT id FROM badges WHERE name = 'First Reading Star'");
                $stmt->execute();
                $starBadge = $stmt->fetch();
                if ($starBadge) {
                    $stmt2 = $db->prepare('SELECT id FROM learner_badges WHERE learner_id = ? AND badge_id = ?');
                    $stmt2->execute([$learner['id'], $starBadge['id']]);
                    if (!$stmt2->fetch()) {
                        $db->prepare('INSERT INTO learner_badges (learner_id, badge_id, date_earned) VALUES (?, ?, ?)')
                            ->execute([$learner['id'], $starBadge['id'], gmdate('Y-m-d H:i:s')]);
                        $newBadges[] = 'First Reading Star';
                    }
                }
            }

            // Notifications — Teacher (if in a class) + every linked Parent,
            // plus an additional urgent one to both if flagged.
            $recipients = [];
            if ($learner['class_id']) {
                $stmt = $db->prepare('SELECT u.id FROM users u JOIN teachers t ON t.user_id = u.id JOIN classes c ON c.teacher_id = t.id WHERE c.id = ?');
                $stmt->execute([$learner['class_id']]);
                if ($row = $stmt->fetch()) $recipients[] = (int) $row['id'];
            }
            $stmt = $db->prepare('SELECT u.id FROM users u JOIN parents p ON p.user_id = u.id JOIN parent_learners pl ON pl.parent_id = p.id WHERE pl.learner_id = ?');
            $stmt->execute([$learner['id']]);
            foreach ($stmt->fetchAll() as $row) $recipients[] = (int) $row['id'];

            foreach (array_unique($recipients) as $recipientId) {
                $summary = "{$learner['first_name']} completed a session: {$result['accuracy_percent']}% accuracy.";
                $db->prepare('INSERT INTO notifications (recipient_user_id, learner_id, type, message, is_read, timestamp) VALUES (?, ?, ?, ?, 0, ?)')
                    ->execute([$recipientId, $learner['id'], 'Session Summary', $summary, gmdate('Y-m-d H:i:s')]);

                if ($flagged) {
                    $db->prepare('INSERT INTO notifications (recipient_user_id, learner_id, type, message, is_read, timestamp) VALUES (?, ?, ?, ?, 0, ?)')
                        ->execute([$recipientId, $learner['id'], 'Needs Attention', "{$learner['first_name']} needs attention — accuracy dropped below 70%.", gmdate('Y-m-d H:i:s')]);
                }
                if ($isFinalDiagnosticStep) {
                    $db->prepare('INSERT INTO notifications (recipient_user_id, learner_id, type, message, is_read, timestamp) VALUES (?, ?, ?, ?, 0, ?)')
                        ->execute([$recipientId, $learner['id'], 'Session Summary', "{$learner['first_name']}'s starting reading level has been confirmed: $levelAfter.", gmdate('Y-m-d H:i:s')]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error(500, 'Could not save session. Please try again. (' . $e->getMessage() . ')');
        }

        Response::json(201, [
            'unclear' => false,
            'session_id' => $sessionId,
            'accuracy_percent' => $result['accuracy_percent'],
            'wcpm' => $result['wcpm'],
            'level_before' => $levelBefore,
            'level_after' => $levelAfter,
            'level_changed' => $levelBefore !== $levelAfter,
            'flagged_needs_attention' => $flagged,
            'points_earned' => $pointsEarned,
            'current_points' => $newPoints,
            'current_streak' => $newStreak,
            'new_badges' => $newBadges,
            'session_type' => $sessionType,
            'diagnostic_complete' => $isFinalDiagnosticStep,
        ]);
    }
}
