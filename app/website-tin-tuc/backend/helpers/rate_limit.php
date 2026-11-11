<?php
/**
 * Rate limit đăng nhập theo cặp account + IP.
 * Sai 5 lần trong 10 phút sẽ bị khóa tạm 15 phút.
 */

const LOGIN_RATE_LIMIT_MAX_ATTEMPTS = 5;
const LOGIN_RATE_LIMIT_WINDOW_SECONDS = 600;
const LOGIN_RATE_LIMIT_BLOCK_SECONDS = 900;

/** Không tin X-Forwarded-For khi chưa có proxy allowlist. */
function getClientIpAddress() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function isLoginRateLimited(PDO $pdo, $account, $ipAddress) {
    $stmt = $pdo->prepare(
        "SELECT blocked_until
         FROM login_attempts
         WHERE account = ? AND ip_address = ?
         LIMIT 1"
    );
    $stmt->execute([$account, $ipAddress]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row && !empty($row['blocked_until'])
        && strtotime($row['blocked_until']) > time();
}

function recordFailedLogin(PDO $pdo, $account, $ipAddress) {
    $now = time();
    $nowSql = date('Y-m-d H:i:s', $now);
    $windowStart = $now - LOGIN_RATE_LIMIT_WINDOW_SECONDS;
    $blockUntilSql = null;

    $pdo->beginTransaction();
    try {
        $rowLock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $stmt = $pdo->prepare(
            "SELECT failed_count, first_failed_at
             FROM login_attempts
             WHERE account = ? AND ip_address = ?" . $rowLock
        );
        $stmt->execute([$account, $ipAddress]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $isOutsideWindow = !$row
            || strtotime($row['first_failed_at']) < $windowStart;
        $failedCount = $isOutsideWindow ? 1 : ((int) $row['failed_count'] + 1);
        $firstFailedAt = $isOutsideWindow ? $nowSql : $row['first_failed_at'];

        if ($failedCount >= LOGIN_RATE_LIMIT_MAX_ATTEMPTS) {
            $blockUntilSql = date(
                'Y-m-d H:i:s',
                $now + LOGIN_RATE_LIMIT_BLOCK_SECONDS
            );
        }

        if (!$row) {
            $insert = $pdo->prepare(
                "INSERT INTO login_attempts
                    (account, ip_address, failed_count, first_failed_at,
                     last_failed_at, blocked_until)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([
                $account, $ipAddress, $failedCount, $firstFailedAt,
                $nowSql, $blockUntilSql,
            ]);
        } else {
            $update = $pdo->prepare(
                "UPDATE login_attempts
                 SET failed_count = ?, first_failed_at = ?,
                     last_failed_at = ?, blocked_until = ?
                 WHERE account = ? AND ip_address = ?"
            );
            $update->execute([
                $failedCount, $firstFailedAt, $nowSql, $blockUntilSql,
                $account, $ipAddress,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'failed_count' => $failedCount,
        'blocked_until' => $blockUntilSql,
        'is_blocked' => $blockUntilSql !== null,
    ];
}

function resetLoginAttempts(PDO $pdo, $account, $ipAddress) {
    $stmt = $pdo->prepare(
        "DELETE FROM login_attempts
         WHERE account = ? AND ip_address = ?"
    );
    $stmt->execute([$account, $ipAddress]);
}

/** Rate limit chung cho các luồng có thể bị spam ngoài đăng nhập. */
function consumeActionRateLimit(PDO $pdo, string $action, string $subject, int $maxRequests, int $windowSeconds): bool
{
    $now = time();
    $windowStart = $now - ($now % $windowSeconds);
    $startedAt = date('Y-m-d H:i:s', $windowStart);
    $updatedAt = date('Y-m-d H:i:s', $now);
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare("SELECT request_count FROM action_rate_limits WHERE action_key = ? AND subject_key = ? AND window_started = ?" . $lock);
        $stmt->execute([$action, $subject, $startedAt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $row ? ((int)$row['request_count'] + 1) : 1;
        if ($row) {
            $update = $pdo->prepare("UPDATE action_rate_limits SET request_count = ?, updated_at = ? WHERE action_key = ? AND subject_key = ? AND window_started = ?");
            $update->execute([$count, $updatedAt, $action, $subject, $startedAt]);
        } else {
            $insert = $pdo->prepare("INSERT INTO action_rate_limits (action_key, subject_key, window_started, request_count, updated_at) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$action, $subject, $startedAt, $count, $updatedAt]);
        }
        $pdo->commit();
        return $count <= $maxRequests;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
