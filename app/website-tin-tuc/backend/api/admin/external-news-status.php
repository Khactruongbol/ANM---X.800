<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

require_once __DIR__ . '/../../helpers/rss.php';

if (!isExternalNewsEnabled()) {
    http_response_code(410);
    jsonResponse(false, null, 'Che do local-only khong cho phep quan ly tin ngoai');
}

requireRole(['admin', 'editor']);
requireCsrfForMutation();

if (($_SERVER['REQUEST_METHOD'] ?? 'PATCH') !== 'PATCH') {
    http_response_code(405);
    header('Allow: PATCH');
    jsonResponse(false, null, 'Phuong thuc khong duoc ho tro');
}

$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$status = (string)($input['status'] ?? '');
if (!$id || !in_array($status, ['approved', 'hidden', 'expired'], true)) {
    jsonResponse(false, null, 'Du lieu cap nhat khong hop le');
}

try {
    $stmt = $pdo->prepare("UPDATE external_articles SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, (int)$id]);
    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        jsonResponse(false, null, 'Khong tim thay tin tham khao');
    }
    writeAuditLog('external_news_status', 'external_article', (int)$id, ['status' => $status]);
    jsonResponse(true, ['id' => (int)$id, 'status' => $status], 'Cap nhat trang thai tin tham khao thanh cong');
} catch (Throwable $error) {
    error_log('External news status failed: ' . $error->getMessage());
    http_response_code(503);
    jsonResponse(false, null, 'Tam thoi khong the cap nhat tin tham khao');
}
