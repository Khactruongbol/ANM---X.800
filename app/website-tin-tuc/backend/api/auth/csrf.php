<?php
/**
 * Cấp CSRF token cho phiên hiện tại.
 * Endpoint này không yêu cầu đăng nhập; token được gắn với PHP session.
 */

require_once '../../helpers/response.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, "Phương thức không được hỗ trợ");
}

jsonResponse(true, [
    'csrf_token' => getCsrfToken(),
], "Lấy CSRF token thành công");
