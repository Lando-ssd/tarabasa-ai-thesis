<?php

require_once __DIR__ . '/Database.php';

class Auth
{
    const MAX_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 15;

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Returns true if identifier is currently locked out.
     */
    public static function isLockedOut(string $identifierType, string $identifier): bool
    {
        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT locked_until FROM login_attempts WHERE identifier_type = ? AND identifier = ?'
        );
        $stmt->execute([$identifierType, $identifier]);
        $row = $stmt->fetch();
        if (!$row || !$row['locked_until']) {
            return false;
        }
        return strtotime($row['locked_until']) > time();
    }

    public static function recordFailedAttempt(string $identifierType, string $identifier): void
    {
        $db = Database::get();
        $stmt = $db->prepare(
            'SELECT failed_count FROM login_attempts WHERE identifier_type = ? AND identifier = ?'
        );
        $stmt->execute([$identifierType, $identifier]);
        $row = $stmt->fetch();

        if (!$row) {
            $db->prepare(
                'INSERT INTO login_attempts (identifier_type, identifier, failed_count, updated_at) VALUES (?, ?, 1, ?)'
            )->execute([$identifierType, $identifier, gmdate('Y-m-d H:i:s')]);
            return;
        }

        $newCount = $row['failed_count'] + 1;
        $lockedUntil = null;
        if ($newCount >= self::MAX_ATTEMPTS) {
            $lockedUntil = gmdate('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
        }
        $db->prepare(
            'UPDATE login_attempts SET failed_count = ?, locked_until = ?, updated_at = ? WHERE identifier_type = ? AND identifier = ?'
        )->execute([$newCount, $lockedUntil, gmdate('Y-m-d H:i:s'), $identifierType, $identifier]);
    }

    public static function resetAttempts(string $identifierType, string $identifier): void
    {
        $db = Database::get();
        $db->prepare(
            'DELETE FROM login_attempts WHERE identifier_type = ? AND identifier = ?'
        )->execute([$identifierType, $identifier]);
    }

    public static function issueToken(string $actorType, int $actorId): string
    {
        $db = Database::get();
        $token = self::generateToken();
        $db->prepare(
            'INSERT INTO auth_tokens (token, actor_type, actor_id, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$token, $actorType, $actorId, gmdate('Y-m-d H:i:s')]);
        return $token;
    }

    public static function resolveToken(string $token): ?array
    {
        $db = Database::get();
        $stmt = $db->prepare('SELECT * FROM auth_tokens WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function revokeToken(string $token): void
    {
        $db = Database::get();
        $db->prepare('DELETE FROM auth_tokens WHERE token = ?')->execute([$token]);
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $header, $m)) {
            return $m[1];
        }
        return null;
    }
}
