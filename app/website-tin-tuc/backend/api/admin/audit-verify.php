<?php
/** API kiểm tra toàn vẹn audit log; chỉ role admin được gọi. */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/audit_verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, 'Phương thức không được hỗ trợ');
}

requireRole(['admin']);
$result = verifyAuditLogChain($pdo);

jsonResponse(
    $result['valid'],
    $result,
    $result['valid']
        ? 'Audit log hợp lệ'
        : 'Audit log không hợp lệ hoặc chưa thể xác minh'
);
