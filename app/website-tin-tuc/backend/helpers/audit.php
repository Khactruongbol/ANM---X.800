<?php
/**
 * Audit log + HMAC/hash chain.
 *
 * Thứ tự lấy khóa: AUDIT_HMAC_KEY rồi tới file khóa local tự sinh.
 * Không ghi secret ra response hoặc audit detail.
 */

function getAuditHmacKey() {
    $environmentKey = getenv('AUDIT_HMAC_KEY');
    if (is_string($environmentKey) && trim($environmentKey) !== '') {
        return trim($environmentKey);
    }

    $localKeyPath = __DIR__ . '/../config/audit.local.key';
    if (is_readable($localKeyPath)) {
        $localKey = trim((string) file_get_contents($localKeyPath));
        if ($localKey !== '') {
            return $localKey;
        }
    }

    // Fallback dành cho lab local: khóa ngẫu nhiên nằm ngoài git.
    try {
        $localKey = bin2hex(random_bytes(32));
        if (@file_put_contents($localKeyPath, $localKey . PHP_EOL, LOCK_EX) !== false) {
            @chmod($localKeyPath, 0600);
            return $localKey;
        }
    } catch (Throwable $e) {
        error_log('Audit HMAC key generation failed: ' . $e->getMessage());
    }

    error_log('Audit HMAC key is unavailable');
    return null;
}

function buildAuditHashPayload(array $data, $previousHash) {
    return json_encode([
        'user_id' => $data['user_id'] ?? null,
        'username' => $data['username'] ?? null,
        'role' => $data['role'] ?? null,
        'action' => $data['action'] ?? null,
        'target_type' => $data['target_type'] ?? null,
        'target_id' => $data['target_id'] ?? null,
        'ip_address' => $data['ip_address'] ?? null,
        'user_agent' => $data['user_agent'] ?? null,
        'detail' => $data['detail'] ?? null,
        'created_at' => $data['created_at'] ?? null,
        'previous_hash' => $previousHash,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
}

function getLatestAuditHash(PDO $pdo) {
    $rowLock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? ' FOR UPDATE'
        : '';
    $stmt = $pdo->query(
        "SELECT current_hash
         FROM audit_logs
         ORDER BY id DESC
         LIMIT 1" . $rowLock
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row['current_hash'] ?? null;
}

function writeAuditLog($action, $targetType = null, $targetId = null, $detail = null) {
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    try {
        $hmacKey = getAuditHmacKey();
        if ($hmacKey === null) {
            return false;
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $username = isset($_SESSION['username']) && is_string($_SESSION['username'])
            ? $_SESSION['username']
            : null;
        $role = isset($_SESSION['role']) && is_string($_SESSION['role'])
            ? $_SESSION['role']
            : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $detailText = is_scalar($detail)
            ? (string) $detail
            : ($detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE));
        $createdAt = date('Y-m-d H:i:s');

        $data = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'action' => (string) $action,
            'target_type' => $targetType !== null ? (string) $targetType : null,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'ip_address' => $ipAddress !== null ? substr((string) $ipAddress, 0, 64) : null,
            'user_agent' => $userAgent !== null ? substr((string) $userAgent, 0, 2000) : null,
            'detail' => $detailText !== null ? substr($detailText, 0, 4000) : null,
            'created_at' => $createdAt,
        ];

        $pdo->beginTransaction();
        $previousHash = getLatestAuditHash($pdo);
        $currentHash = hash_hmac(
            'sha256',
            buildAuditHashPayload($data, $previousHash),
            $hmacKey
        );

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs
                (user_id, username, role, action, target_type, target_id,
                 ip_address, user_agent, detail, previous_hash, current_hash,
                 created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['user_id'], $data['username'], $data['role'], $data['action'],
            $data['target_type'], $data['target_id'], $data['ip_address'],
            $data['user_agent'], $data['detail'], $previousHash, $currentHash,
            $createdAt,
        ]);
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Audit log write failed: ' . $e->getMessage());
        return false;
    }
}
