<?php
/**
 * ==============================================================================
 * TÊN FILE: backend/helpers/auth.php
 * PHÂN HỆ: Trợ giúp Xác thực & Phân quyền (Backend Auth Helper)
 * MÔ TẢ: Khởi tạo PHP Session và cung cấp các hàm kiểm tra đăng nhập, phân quyền vai trò.
 * PHẠM VI SỬ DỤNG:
 *   - [TẬP TIN DÙNG CHUNG CỐT LÕI]
 *   - Phụ thuộc: backend/helpers/response.php (gọi jsonResponse khi từ chối truy cập)
 *   - Được require_once bởi: Tất cả các API yêu cầu đăng nhập thuộc
 *     backend/api/{user, reporter, editor, admin}/** và backend/api/upload.php
 * ==============================================================================
 */

require_once __DIR__ . '/audit.php';

// Khởi tạo session đúng một lần với các cookie flags an toàn.
// Secure chỉ bật khi request thực sự chạy qua HTTPS để local HTTP vẫn hoạt động.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Danh sách role hợp lệ của workflow website.
 * Đây là allowlist dùng để tránh khai báo nhầm quyền ở endpoint mới.
 */
const APP_ROLES = ['admin', 'editor', 'reporter', 'user'];

/**
 * Trả về role hiện tại sau khi chuẩn hóa.
 *
 * @return string|null
 */
function getCurrentRole() {
    $role = $_SESSION['role'] ?? null;
    return is_string($role) ? $role : null;
}

/**
 * [HÀM DÙNG CHUNG] requireLogin
 * - Chức năng: Kiểm tra người dùng hiện tại đã đăng nhập vào hệ thống hay chưa thông qua PHP Session.
 * - Hành vi: Nếu chưa đăng nhập ($_SESSION['user_id'] chưa tồn tại), trả về lỗi 
 *   JSON { success: false, message: "Bạn cần đăng nhập" } và dừng thực thi ngay.
 * - Được gọi bởi: requireRole(), backend/api/user/**, backend/api/upload.php.
 * 
 * @return void
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        writeAuditLog('access_denied', 'endpoint', null, 'login_required');
        jsonResponse(false, null, "Bạn cần đăng nhập");
    }

    // Không tin role/status cũ trong session sau khi admin thay đổi tài khoản.
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['status'] !== 'active' || !in_array($user['role'], APP_ROLES, true)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
            }
            session_destroy();
            writeAuditLog('access_denied', 'authentication', null, 'inactive_or_deleted_account');
            jsonResponse(false, null, "Phiên đăng nhập không hợp lệ");
        }
        $_SESSION['role'] = $user['role'];
    }
}

/**
 * [HÀM DÙNG CHUNG] requireRole
 * - Chức năng: Kiểm tra quyền truy cập dựa trên danh sách các vai trò (roles) được phép.
 * - Các vai trò trong hệ thống: 'admin', 'editor', 'reporter', 'user'.
 * - Hành vi: Tự động gọi requireLogin() trước, sau đó nếu vai trò hiện tại không nằm trong
 *   mảng $roles thì trả về lỗi { success: false, message: "Không có quyền truy cập" } và dừng thực thi.
 * - Được gọi bởi: 
 *   + backend/api/admin/** (cho phép ['admin'])
 *   + backend/api/editor/** (cho phép ['editor', 'admin'])
 *   + backend/api/reporter/** (cho phép ['reporter', 'admin'])
 * 
 * @param array $roles Mảng danh sách các chuỗi vai trò được cấp quyền
 * @return void
 */
function requireRole($roles) {
    requireLogin();

    if (!is_array($roles) || empty($roles)) {
        jsonResponse(false, null, "Cấu hình quyền không hợp lệ");
    }

    // Chỉ cho phép endpoint yêu cầu các role đã được định nghĩa trong workflow.
    $roles = array_values(array_intersect($roles, APP_ROLES));
    $currentRole = getCurrentRole();

    // So sánh strict để không chấp nhận giá trị khác kiểu ngoài allowlist.
    if (empty($roles) || !in_array($currentRole, $roles, true)) {
        writeAuditLog('access_denied', 'endpoint', null, 'role_required');
        jsonResponse(false, null, "Không có quyền truy cập");
    }
}
