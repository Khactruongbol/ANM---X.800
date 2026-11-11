<?php
/**
 * ==============================================================================
 * TÊN FILE: backend/api/auth/login.php
 * PHÂN HỆ: API Xác thực & Đăng nhập (Auth Service)
 * MÔ TẢ: Xử lý đăng nhập tài khoản người dùng bằng Email hoặc Username và mật khẩu.
 * PHẠM VI SỬ DỤNG:
 *   - [API CÔNG KHAI - AUTH]
 *   - Phương thức: POST
 * PHỤ THUỘC (HELPERS):
 *   - backend/config/database.php ($pdo)
 *   - backend/helpers/response.php (jsonResponse)
 *   - backend/helpers/auth.php (session_start)
 * ĐƯỢC GỌI BỞI (FRONTEND):
 *   - frontend/assets/js/login.js (Hàm xử lý form đăng nhập)
 * ĐẦU VÀO (POST):
 *   - email: Email hoặc Username của người dùng
 *   - password: Mật khẩu dạng plain text
 * TRẢ VỀ (JSON):
 *   - Thành công: { success: true, data: { id, username, role }, message: "Đăng nhập thành công" }
 *   - Thất bại: { success: false, data: { lock_reason? }, message: "..." }
 * ==============================================================================
 */

require_once '../../config/database.php';
require_once '../../helpers/response.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/rate_limit.php';

// ==============================================================================
// KHỐI 1: KIỂM TRA PHƯƠNG THỨC HTTP
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, "Phương thức không được hỗ trợ");
}
requireCsrfForMutation();

// ==============================================================================
// KHỐI 2: TIẾP NHẬN & KIỂM TRA DỮ LIỆU ĐẦU VÀO
// - Hỗ trợ đăng nhập linh hoạt bằng cả Email hoặc Username
// ==============================================================================
$account = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($account === '' || $password === '') {
    jsonResponse(false, null, "Vui lòng nhập đầy đủ email/username và mật khẩu");
}

$ipAddress = getClientIpAddress();

// ==============================================================================
// KHỐI 3: TRUY VẤN TÀI KHOẢN & XÁC MINH MẬT KHẨU
// ==============================================================================
try {
    if (isLoginRateLimited($pdo, $account, $ipAddress)) {
        writeAuditLog('login_rate_limited', 'authentication', null, 'rate_limit_blocked');
        jsonResponse(false, null, "Đăng nhập tạm thời bị giới hạn, vui lòng thử lại sau");
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$account, $account]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        $attempt = recordFailedLogin($pdo, $account, $ipAddress);
        if ($attempt['is_blocked']) {
            writeAuditLog('login_rate_limited', 'authentication', null, 'threshold_reached');
            jsonResponse(false, null, "Đăng nhập tạm thời bị giới hạn, vui lòng thử lại sau");
        }
        writeAuditLog('login_failed', 'authentication', null, 'invalid_account_or_password');
        jsonResponse(false, null, "Email/Username hoặc mật khẩu không chính xác");
    }

    // Kiểm tra trạng thái tài khoản nếu đang bị khóa
    if ($user['status'] === 'locked') {
        jsonResponse(false, [
            "lock_reason" => $user['lock_reason'],
        ], "Tài khoản này đã bị khóa quyền truy cập");
    }

    resetLoginAttempts($pdo, $account, $ipAddress);

    // ==============================================================================
    // KHỐI 4: KHỞI TẠO PHIÊN LÀM VIỆC (SESSION)
    // - Tái tạo session ID (session_regenerate_id) để phòng chống tấn công Session Fixation
    // - Lưu user_id và role vào $_SESSION
    // ==============================================================================
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];

    writeAuditLog('login_success', 'user', $user['id'], 'authenticated');

    jsonResponse(true, [
        "id" => $user['id'],
        "username" => $user['username'],
        "role" => $user['role'],
    ], "Đăng nhập thành công");
} catch (PDOException $e) {
    jsonResponse(false, null, "Lỗi hệ thống, vui lòng thử lại sau");
}

