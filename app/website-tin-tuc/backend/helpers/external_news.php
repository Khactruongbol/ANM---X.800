<?php

require_once __DIR__ . '/rss.php';

function externalNewsHash(array $item): string
{
    $canonical = strtolower(trim((string)($item['url'] ?? '')));
    return hash('sha256', (string)($item['source_key'] ?? '') . "\n" . (string)($item['title'] ?? '') . "\n" . $canonical);
}

function externalNewsUrlIsAllowed(array $item): bool
{
    foreach (getConfiguredRssSources() as $source) {
        if ((string)$source['key'] === (string)($item['source_key'] ?? '')) {
            return isAllowedRssArticleUrl((string)($item['url'] ?? ''), $source);
        }
    }
    return false;
}

function syncExternalNews(PDO $pdo, array $items): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO external_articles
            (source_key, source_name, title, summary, original_url, canonical_url,
             published_at, fetched_at, content_hash, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'approved')
         ON DUPLICATE KEY UPDATE
            source_name = VALUES(source_name), title = VALUES(title),
            summary = VALUES(summary), original_url = VALUES(original_url),
            canonical_url = VALUES(canonical_url), published_at = VALUES(published_at),
            fetched_at = NOW(), updated_at = NOW()"
    );
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item) || !externalNewsUrlIsAllowed($item)) continue;
        $hash = externalNewsHash($item);
        $published = !empty($item['published_at']) ? date('Y-m-d H:i:s', strtotime((string)$item['published_at'])) : null;
        $stmt->execute([
            (string)$item['source_key'], (string)$item['source_name'],
            (string)$item['title'], (string)($item['summary'] ?? ''),
            (string)$item['url'], (string)$item['url'], $published, $hash,
        ]);
        $count++;
    }
    return $count;
}

function mapExternalNewsRow(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'source_key' => $row['source_key'],
        'source_name' => $row['source_name'],
        'attribution' => 'Theo ' . $row['source_name'],
        'title' => $row['title'],
        'summary' => $row['summary'],
        'original_url' => $row['original_url'],
        'published_at' => $row['published_at'],
        'status' => $row['status'],
    ];
}
