<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

class AdminController
{
    /**
     * Resolves the bearer token to a User row and confirms user_type = Admin.
     * Every Admin route calls this first — this is the real security boundary,
     * not whatever the frontend shows/hides.
     */
    private static function requireAdmin(): array
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
        if (!$user || $user['user_type'] !== 'Admin') {
            Response::error(403, 'Admin access required.');
        }
        return $user;
    }

    // ============================================================
    // GET /api/admin/pending-teachers
    // ============================================================
    public static function pendingTeachers(): void
    {
        self::requireAdmin();
        $db = Database::get();
        $stmt = $db->query(
            "SELECT t.id as teacher_id, u.first_name, u.last_name, u.email,
                    t.school_name, t.employee_id, u.created_at
             FROM teachers t
             JOIN users u ON u.id = t.user_id
             WHERE t.status = 'Pending'
             ORDER BY u.created_at ASC"
        );
        Response::json(200, ['pending_teachers' => $stmt->fetchAll()]);
    }

    // ============================================================
    // POST /api/admin/teacher-status   { teacher_id, status: Active|Rejected }
    // Freely changeable between Pending/Active/Rejected at any time
    // (edge case: re-activating an Active one, re-rejecting a Rejected one — both allowed).
    // ============================================================
    public static function teacherStatus(array $body): void
    {
        self::requireAdmin();

        if (empty($body['teacher_id']) || empty($body['status'])) {
            Response::error(422, 'teacher_id and status are required.');
        }
        if (!in_array($body['status'], ['Active', 'Rejected', 'Pending'], true)) {
            Response::error(422, 'status must be one of Active, Rejected, Pending.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM teachers WHERE id = ?');
        $stmt->execute([$body['teacher_id']]);
        $teacher = $stmt->fetch();
        if (!$teacher) {
            Response::error(404, 'Teacher not found.');
        }

        $db->prepare('UPDATE teachers SET status = ? WHERE id = ?')
            ->execute([$body['status'], $body['teacher_id']]);

        Response::json(200, [
            'message' => "Teacher status updated to {$body['status']}.",
            'teacher_id' => (int) $body['teacher_id'],
            'status' => $body['status'],
        ]);
    }

    // ============================================================
    // GET /api/admin/all-users
    // Every non-Admin user (Teachers and Parents). Admin never appears
    // in its own manageable list — structurally excluded by the query.
    // ============================================================
    public static function allUsers(): void
    {
        self::requireAdmin();
        $db = Database::get();
        $stmt = $db->query(
            "SELECT id, first_name, last_name, email, user_type, status, created_at
             FROM users
             WHERE user_type != 'Admin'
             ORDER BY created_at DESC"
        );
        Response::json(200, ['users' => $stmt->fetchAll()]);
    }

    // ============================================================
    // POST /api/admin/toggle-user-status   { user_id }
    // Flips Active<->Inactive. Applies to Teachers AND Parents alike.
    // Structurally cannot target an Admin account (Rule 1).
    // ============================================================
    public static function toggleUserStatus(array $body): void
    {
        self::requireAdmin();

        if (empty($body['user_id'])) {
            Response::error(422, 'user_id is required.');
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$body['user_id']]);
        $target = $stmt->fetch();
        if (!$target) {
            Response::error(404, 'User not found.');
        }
        if ($target['user_type'] === 'Admin') {
            Response::error(403, 'Admin accounts cannot be toggled.');
        }

        $newStatus = $target['status'] === 'Active' ? 'Inactive' : 'Active';
        $db->prepare('UPDATE users SET status = ? WHERE id = ?')
            ->execute([$newStatus, $body['user_id']]);

        Response::json(200, [
            'message' => "User status updated to $newStatus.",
            'user_id' => (int) $body['user_id'],
            'status' => $newStatus,
        ]);
    }
}
