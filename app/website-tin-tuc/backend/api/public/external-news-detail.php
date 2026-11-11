<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/external_news.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    jsonResponse(false, null, 'Phuong thuc khong duoc ho tro');
}

if (!isExternalNewsEnabled()) {
    http_response_code(410);
    jsonResponse(false, null, 'Website dang o che do noi dung noi bo; tin ngoai da duoc tat');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) jsonResponse(false, null, 'Tin tham khao khong hop le');

try {
    $stmt = $pdo->prepare("SELECT id, source_key, source_name, title, summary, original_url, published_at, status FROM external_articles WHERE id = ? AND status = 'approved' LIMIT 1");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        jsonResponse(false, null, 'Khong tim thay tin tham khao');
    }
    if (!externalNewsUrlIsAllowed(['source_key' => $row['source_key'], 'url' => $row['original_url']])) {
        http_response_code(410);
        jsonResponse(false, null, 'Nguon tin khong con duoc cho phep');
    }
    jsonResponse(true, mapExternalNewsRow($row), 'Tai tin tham khao thanh cong');
} catch (Throwable $error) {
    error_log('External news detail failed: ' . $error->getMessage());
    http_response_code(503);
    jsonResponse(false, null, 'Tam thoi khong the tai tin tham khao');
}
