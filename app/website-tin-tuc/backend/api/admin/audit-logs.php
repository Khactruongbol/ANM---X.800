<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';

requireRole(['admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    jsonResponse(false, null, 'Phuong thuc khong duoc ho tro');
}

[$page, $limit, $offset] = getPaginationParams(25, 100);
$where = [];
$params = [];

$action = trim((string)($_GET['action'] ?? ''));
if ($action !== '') {
    $where[] = 'action = :action';
    $params[':action'] = substr($action, 0, 100);
}

$username = trim((string)($_GET['username'] ?? ''));
if ($username !== '') {
    $where[] = 'username LIKE :username';
    $params[':username'] = '%' . substr($username, 0, 100) . '%';
}

$from = trim((string)($_GET['from'] ?? ''));
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'created_at >= :from_date';
    $params[':from_date'] = $from . ' 00:00:00';
}

$to = trim((string)($_GET['to'] ?? ''));
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'created_at <= :to_date';
    $params[':to_date'] = $to . ' 23:59:59';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs{$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, user_id, username, role, action, target_type, target_id,
                ip_address, user_agent, detail, created_at
         FROM audit_logs{$whereSql}
         ORDER BY id DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    jsonPaginatedResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC), $total, $page, $limit, 'Tai audit log thanh cong');
} catch (Throwable $error) {
    error_log('Audit log list failed: ' . $error->getMessage());
    http_response_code(503);
    jsonResponse(false, null, 'Tam thoi khong the tai audit log');
}
