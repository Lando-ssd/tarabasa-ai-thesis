<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

class AuthController
{
    // ============================================================
    // POST /api/auth/register-teacher
    // ============================================================
    public static function registerTeacher(array $body): void
    {
        $required = ['first_name', 'last_name', 'email', 'password', 'school_name', 'employee_id'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error(422, "Field '$field' is required.");
            }
        }
        if (strlen($body['password']) < 8) {
            Response::error(422, 'Password must be at least 8 characters.');
        }
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error(422, 'A valid email address is required.');
        }

        $db = Database::get();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$body['email']]);
        if ($stmt->fetch()) {
            Response::error(409, 'An account with this email already exists.');
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO users (first_name, last_name, email, password, user_type, contact_number, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $body['first_name'],
                $body['last_name'],
                $body['email'],
                Auth::hashPassword($body['password']),
                'Teacher',
                $body['contact_number'] ?? null,
                'Active', // account-level status; teacher-level verification status is separate
                gmdate('Y-m-d H:i:s'),
            ]);
            $userId = (int) $db->lastInsertId();

            $db->prepare(
                'INSERT INTO teachers (user_id, school_name, employee_id, status, free_generation_credits_remaining)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$userId, $body['school_name'], $body['employee_id'], 'Pending', 2]);
            $teacherId = (int) $db->lastInsertId();

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error(500, 'Registration failed. Please try again.');
        }

        // Teacher can log in immediately even while Pending (per Teacher Actor Prompt Step 2),
        // so we issue a token right away rather than forcing a separate login step.
        $token = Auth::issueToken('user', $userId);

        Response::json(201, [
            'message' => "Your account is pending Admin approval. You can log in now — some features will be limited until you're approved.",
            'token' => $token,
            'user' => [
                'id' => $userId,
                'first_name' => $body['first_name'],
                'last_name' => $body['last_name'],
                'email' => $body['email'],
                'user_type' => 'Teacher',
            ],
            'teacher' => [
                'id' => $teacherId,
                'status' => 'Pending',
                'free_generation_credits_remaining' => 2,
            ],
        ]);
    }

    // ============================================================
    // POST /api/auth/register-parent
    // ============================================================
    public static function registerParent(array $body): void
    {
        $required = ['first_name', 'last_name', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                Response::error(422, "Field '$field' is required.");
            }
        }
        if (strlen($body['password']) < 8) {
            Response::error(422, 'Password must be at least 8 characters.');
        }
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error(422, 'A valid email address is required.');
        }

        $db = Database::get();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$body['email']]);
        if ($stmt->fetch()) {
            Response::error(409, 'An account with this email already exists.');
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO users (first_name, last_name, email, password, user_type, contact_number, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $body['first_name'],
                $body['last_name'],
                $body['email'],
                Auth::hashPassword($body['password']),
                'Parent',
                $body['contact_number'] ?? null,
                'Active', // no approval gate for Parents — Active immediately
                gmdate('Y-m-d H:i:s'),
            ]);
            $userId = (int) $db->lastInsertId();

            $db->prepare('INSERT INTO parents (user_id) VALUES (?)')->execute([$userId]);
            $parentId = (int) $db->lastInsertId();

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Response::error(500, 'Registration failed. Please try again.');
        }

        $token = Auth::issueToken('user', $userId);

        Response::json(201, [
            'message' => 'Account created.',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'first_name' => $body['first_name'],
                'last_name' => $body['last_name'],
                'email' => $body['email'],
                'user_type' => 'Parent',
            ],
            'parent' => ['id' => $parentId],
        ]);
    }

    // ============================================================
    // POST /api/auth/login  (Admin / Teacher / Parent)
    // ============================================================
    public static function login(array $body): void
    {
        if (empty($body['email']) || empty($body['password'])) {
            Response::error(422, 'Email and password are required.');
        }

        $email = $body['email'];

        if (Auth::isLockedOut('email', $email)) {
            Response::error(429, 'Too many failed attempts. Please try again in 15 minutes.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Generic error for both "no such user" and "wrong password" — never reveal which.
        if (!$user || !Auth::verifyPassword($body['password'], $user['password'])) {
            Auth::recordFailedAttempt('email', $email);
            Response::error(401, 'Invalid email or password.');
        }

        // Deactivation IS enforced here with a specific message (distinct from the
        // generic wrong-password case above) per the Backend Implementation Prompt.
        if ($user['status'] === 'Inactive') {
            Response::error(403, 'This account has been deactivated. Please contact your administrator.');
        }

        $roleData = [];
        if ($user['user_type'] === 'Teacher') {
            $stmt = $db->prepare('SELECT * FROM teachers WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $teacher = $stmt->fetch();

            if ($teacher['status'] === 'Rejected') {
                Response::error(403, 'Your teacher account registration was not approved. Please contact your school administrator.');
            }
            // Pending is allowed to log in — the boundary is "touching real students," not "logging in."
            $roleData = [
                'teacher' => [
                    'id' => $teacher['id'],
                    'status' => $teacher['status'],
                    'school_name' => $teacher['school_name'],
                    'employee_id' => $teacher['employee_id'],
                    'free_generation_credits_remaining' => (int) $teacher['free_generation_credits_remaining'],
                ],
            ];
        } elseif ($user['user_type'] === 'Parent') {
            $stmt = $db->prepare('SELECT * FROM parents WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $parent = $stmt->fetch();
            $roleData = ['parent' => ['id' => $parent['id']]];
        }

        Auth::resetAttempts('email', $email);
        $token = Auth::issueToken('user', (int) $user['id']);

        Response::json(200, array_merge([
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
            ],
        ], $roleData));
    }

    // ============================================================
    // POST /api/auth/logout
    // ============================================================
    public static function logout(): void
    {
        $token = Auth::bearerToken();
        if (!$token) {
            Response::error(401, 'No token provided.');
        }
        Auth::revokeToken($token);
        Response::json(200, ['message' => 'Logged out.']);
    }

    // ============================================================
    // GET /api/auth/me
    // ============================================================
    public static function me(): void
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
        if (!$user) {
            Response::error(401, 'Invalid or expired token.');
        }
        if ($user['status'] === 'Inactive') {
            Response::error(403, 'This account has been deactivated.');
        }

        $roleData = [];
        if ($user['user_type'] === 'Teacher') {
            $stmt = $db->prepare('SELECT * FROM teachers WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $teacher = $stmt->fetch();
            $roleData = ['teacher' => [
                'id' => $teacher['id'],
                'status' => $teacher['status'],
                'school_name' => $teacher['school_name'],
                'employee_id' => $teacher['employee_id'],
                'free_generation_credits_remaining' => (int) $teacher['free_generation_credits_remaining'],
            ]];
        } elseif ($user['user_type'] === 'Parent') {
            $stmt = $db->prepare('SELECT * FROM parents WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $parent = $stmt->fetch();
            $roleData = ['parent' => ['id' => $parent['id']]];
        }

        Response::json(200, array_merge([
            'user' => [
                'id' => (int) $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
            ],
        ], $roleData));
    }

    // ============================================================
    // POST /api/learner/login-pin
    // Accepts EITHER learner_code (QR/code-entry path) OR learner_id
    // (avatar-tap path, where the code was already resolved client-side
    // from a logged-in Parent session) — both converge on the same PIN check.
    // ============================================================
    public static function learnerLoginPin(array $body): void
    {
        if (empty($body['pin']) || (empty($body['learner_code']) && empty($body['learner_id']))) {
            Response::error(422, 'learner_code (or learner_id) and pin are required.');
        }

        $db = Database::get();

        if (!empty($body['learner_code'])) {
            $identifier = $body['learner_code'];
            $stmt = $db->prepare('SELECT * FROM learners WHERE learner_code = ?');
            $stmt->execute([$identifier]);
        } else {
            $identifier = (string) $body['learner_id'];
            $stmt = $db->prepare('SELECT * FROM learners WHERE id = ?');
            $stmt->execute([$body['learner_id']]);
        }

        if (Auth::isLockedOut('learner_id', $identifier)) {
            Response::error(429, 'Too many incorrect attempts. Please try again in 15 minutes.');
        }

        $learner = $stmt->fetch();

        // Never reveal whether the code/id itself was valid — same generic message either way.
        if (!$learner || (string) $learner['pin'] !== (string) $body['pin']) {
            Auth::recordFailedAttempt('learner_id', $identifier);
            Response::error(401, 'Incorrect PIN.');
        }

        Auth::resetAttempts('learner_id', $identifier);
        $token = Auth::issueToken('learner', (int) $learner['id']);

        // hasCompletedDiagnostic check — Placement Diagnostic Addition, Part 2:
        // presence of any Diagnostic ReadingSession row, checked on every successful login.
        $stmt = $db->prepare(
            "SELECT COUNT(*) as cnt FROM reading_sessions WHERE learner_id = ? AND session_type = 'Diagnostic'"
        );
        $stmt->execute([$learner['id']]);
        $hasCompletedDiagnostic = ((int) $stmt->fetch()['cnt']) > 0;

        Response::json(200, [
            'token' => $token,
            'learner' => [
                'id' => (int) $learner['id'],
                'learner_code' => $learner['learner_code'],
                'first_name' => $learner['first_name'],
                'grade_level' => $learner['grade_level'],
                'avatar_id' => $learner['avatar_id'],
                'mastery_level' => $learner['mastery_level'],
                'points' => (int) $learner['points'],
                'streak' => (int) $learner['streak'],
                'class_id' => $learner['class_id'],
            ],
            'has_completed_diagnostic' => $hasCompletedDiagnostic,
        ]);
    }

    // ============================================================
    // GET /api/learner/me
    // ============================================================
    public static function learnerMe(): void
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

        $stmt = $db->prepare(
            "SELECT COUNT(*) as cnt FROM reading_sessions WHERE learner_id = ? AND session_type = 'Diagnostic'"
        );
        $stmt->execute([$learner['id']]);
        $hasCompletedDiagnostic = ((int) $stmt->fetch()['cnt']) > 0;

        Response::json(200, [
            'learner' => [
                'id' => (int) $learner['id'],
                'learner_code' => $learner['learner_code'],
                'first_name' => $learner['first_name'],
                'grade_level' => $learner['grade_level'],
                'avatar_id' => $learner['avatar_id'],
                'mastery_level' => $learner['mastery_level'],
                'points' => (int) $learner['points'],
                'streak' => (int) $learner['streak'],
                'class_id' => $learner['class_id'],
            ],
            'has_completed_diagnostic' => $hasCompletedDiagnostic,
        ]);
    }

    // ============================================================
    // POST /api/learner/logout
    // ============================================================
    public static function learnerLogout(): void
    {
        $token = Auth::bearerToken();
        if (!$token) {
            Response::error(401, 'No token provided.');
        }
        Auth::revokeToken($token);
        Response::json(200, ['message' => 'Learner session ended.']);
    }
}
