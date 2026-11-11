<?php
/** Xác minh toàn bộ chuỗi audit log bằng HMAC/hash chain. */

require_once __DIR__ . '/audit.php';

function verifyAuditLogChain(PDO $connection = null) {
    global $pdo;
    $connection = $connection instanceof PDO ? $connection : $pdo;

    if (!($connection instanceof PDO)) {
        return [
            'valid' => false,
            'checked_count' => 0,
            'error_id' => null,
            'error' => 'Database connection unavailable',
        ];
    }

    $hmacKey = getAuditHmacKey();
    if ($hmacKey === null) {
        return [
            'valid' => false,
            'checked_count' => 0,
            'error_id' => null,
            'error' => 'Audit HMAC key unavailable',
        ];
    }

    try {
        $stmt = $connection->query(
            "SELECT id, user_id, username, role, action, target_type,
                    target_id, ip_address, user_agent, detail,
                    previous_hash, current_hash, created_at
             FROM audit_logs
             ORDER BY id ASC"
        );

        $expectedPreviousHash = null;
        $checkedCount = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $checkedCount++;
            if (!is_string($row['current_hash']) || $row['current_hash'] === '') {
                return [
                    'valid' => false,
                    'checked_count' => $checkedCount,
                    'error_id' => (int) $row['id'],
                    'error' => 'Missing current_hash',
                ];
            }

            $rowPreviousHash = $row['previous_hash'] !== null
                ? (string) $row['previous_hash']
                : null;
            if ($rowPreviousHash !== $expectedPreviousHash) {
                return [
                    'valid' => false,
                    'checked_count' => $checkedCount,
                    'error_id' => (int) $row['id'],
                    'error' => 'Hash chain link mismatch',
                ];
            }

            $expectedHash = hash_hmac(
                'sha256',
                buildAuditHashPayload($row, $rowPreviousHash),
                $hmacKey
            );
            if (!hash_equals($expectedHash, (string) $row['current_hash'])) {
                return [
                    'valid' => false,
                    'checked_count' => $checkedCount,
                    'error_id' => (int) $row['id'],
                    'error' => 'HMAC mismatch',
                ];
            }

            $expectedPreviousHash = (string) $row['current_hash'];
        }

        return [
            'valid' => true,
            'checked_count' => $checkedCount,
            'error_id' => null,
            'error' => null,
        ];
    } catch (Throwable $e) {
        error_log('Audit log verification failed: ' . $e->getMessage());
        return [
            'valid' => false,
            'checked_count' => 0,
            'error_id' => null,
            'error' => 'Audit log verification unavailable',
        ];
    }
}
