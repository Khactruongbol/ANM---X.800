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

try {
    [$page, $limit, $offset] = getPaginationParams(8, 24);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM external_articles WHERE status = 'approved'")->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT id, source_key, source_name, title, summary, original_url, published_at, status
         FROM external_articles WHERE status = 'approved'
         ORDER BY COALESCE(published_at, fetched_at) DESC LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_map('mapExternalNewsRow', $stmt->fetchAll(PDO::FETCH_ASSOC));
    jsonPaginatedResponse(true, $rows, $count, $page, $limit, 'Lay tin tham khao thanh cong');
} catch (Throwable $error) {
    error_log('External news list failed: ' . $error->getMessage());
    http_response_code(503);
    jsonResponse(false, null, 'Tam thoi khong the tai tin tham khao');
}
