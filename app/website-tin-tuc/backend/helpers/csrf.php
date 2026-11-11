<?php
/**
 * Trợ giúp CSRF cho các request làm thay đổi dữ liệu.
 * Phụ thuộc: session đã được khởi tạo bởi helpers/auth.php.
 */

function getCsrfToken() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requireCsrfToken() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        writeAuditLog('csrf_failed', 'endpoint', null, 'invalid_or_missing_token');
        jsonResponse(false, null, "CSRF token không hợp lệ");
    }
}

/**
 * Chỉ kiểm tra CSRF cho các phương thức có thể thay đổi trạng thái.
 * GET/HEAD/OPTIONS là các phương thức đọc hoặc bắt tay nên không yêu cầu token.
 */
function requireCsrfForMutation() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        requireCsrfToken();
    }
}
