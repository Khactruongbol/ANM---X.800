<?php

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/rss.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    jsonResponse(false, null, 'Phuong thuc khong duoc ho tro');
}

if (!isExternalNewsEnabled()) {
    http_response_code(410);
    jsonResponse(false, null, 'Website dang o che do noi dung noi bo; RSS da duoc tat');
}

$source = isset($_GET['source']) ? trim((string)$_GET['source']) : null;
$limit = isset($_GET['limit']) ? max(3, min(10, (int)$_GET['limit'])) : 8;

try {
    $data = getRssNews($source, $limit);
    jsonResponse(true, $data, empty($data['items']) ? 'Chua co tin RSS kha dung' : 'Lay tin RSS thanh cong');
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    jsonResponse(false, null, 'Nguon RSS khong hop le');
} catch (Throwable $error) {
    error_log('RSS endpoint failed');
    http_response_code(503);
    jsonResponse(false, null, 'Tam thoi khong the cap nhat RSS');
}
