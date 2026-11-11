<?php
/**
 * ==============================================================================
 * TÊN FILE: backend/api/auth/logout.php
 * PHÂN HỆ: API Xác thực & Đăng nhập (Auth Service)
 * MÔ TẢ: Hủy toàn bộ thông tin phiên làm việc (session) và cookie phiên của người dùng.
 * PHẠM VI SỬ DỤNG:
 *   - [API CÔNG KHAI - AUTH]
 *   - Phương thức: POST
 * PHỤ THUỘC (HELPERS):
 *   - backend/config/database.php
 *   - backend/helpers/response.php (jsonResponse)
 *   - backend/helpers/auth.php (session_start)
 * ĐƯỢC GỌI BỞI (FRONTEND):
 *   - frontend/assets/js/common.js (Hàm handleLogout dùng chung cho cả Header công khai và Sidebar tòa soạn)
 * TRẢ VỀ (JSON):
 *   - { success: true, message: "Đăng xuất thành công", data: null }
 * ==============================================================================
 */

require_once '../../config/database.php';
require_once '../../helpers/response.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/csrf.php';

// ==============================================================================
// KHỐI 1: KIỂM TRA PHƯƠNG THỨC HTTP
// - Chỉ chấp nhận phương thức POST để bảo vệ chống CSRF khi đăng xuất
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, "Phương thức không được hỗ trợ");
}
requireCsrfForMutation();

// ==============================================================================
// KHỐI 2: HỦY DỮ LIỆU PHIÊN (SESSION) VÀ XÓA COOKIE TRÊN TRÌNH DUYỆT
// ==============================================================================
writeAuditLog('logout', 'authentication', $_SESSION['user_id'] ?? null, 'session_destroyed');
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

// ==============================================================================
// KHỐI 3: PHẢN HỒI KẾT QUẢ ĐĂNG XUẤT CHO CLIENT
// ==============================================================================
jsonResponse(true, null, "Đăng xuất thành công");

