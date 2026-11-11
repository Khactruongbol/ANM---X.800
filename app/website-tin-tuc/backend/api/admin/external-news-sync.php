<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/rate_limit.php';
require_once __DIR__ . '/../../helpers/external_news.php';

if (!isExternalNewsEnabled()) {
    http_response_code(410);
    jsonResponse(false, null, 'Che do local-only khong cho phep dong bo tin ngoai');
}

requireRole(['admin', 'editor']);
requireCsrfForMutation();

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    jsonResponse(false, null, 'Phuong thuc khong duoc ho tro');
}

try {
    $subject = 'user:' . (int)($_SESSION['user_id'] ?? 0);
    if (!consumeActionRateLimit($pdo, 'external_news_sync', $subject, 3, 300)) {
        http_response_code(429);
        jsonResponse(false, null, 'Toc do dong bo qua nhanh, vui long thu lai sau');
    }
    $items = [];
    $fetchedAt = null;
    $fromCache = true;
    foreach (getConfiguredRssSources() as $source) {
        $data = getRssNews((string)$source['key'], RSS_MAX_ITEMS);
        $items = array_merge($items, $data['items'] ?? []);
        $fetchedAt = $data['fetched_at'] ?? $fetchedAt;
        $fromCache = $fromCache && !empty($data['from_cache']);
    }
    $count = syncExternalNews($pdo, $items);
    writeAuditLog('external_news_sync', 'external_articles', null, ['items' => $count, 'from_cache' => $fromCache]);
    jsonResponse(true, ['items_synced' => $count, 'fetched_at' => $fetchedAt, 'source_count' => count(getConfiguredRssSources())], 'Dong bo tin tham khao thanh cong');
} catch (Throwable $error) {
    error_log('External news sync failed: ' . $error->getMessage());
    http_response_code(503);
    jsonResponse(false, null, 'Tam thoi khong the dong bo tin tham khao');
}
