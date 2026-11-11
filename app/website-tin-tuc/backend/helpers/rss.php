<?php

const RSS_CACHE_TTL = 300;
const RSS_MAX_RESPONSE_BYTES = 1048576;
const RSS_MAX_ITEMS = 10;
const RSS_CACHE_MAX_ITEMS = 50;

function isExternalNewsEnabled(): bool
{
    static $enabled;
    if ($enabled === null) {
        $features = require __DIR__ . '/../config/site_features.php';
        $enabled = !empty($features['external_news_enabled']);
    }
    return $enabled;
}

function getConfiguredRssSources(): array
{
    if (!isExternalNewsEnabled()) {
        return [];
    }
    $sources = require __DIR__ . '/../config/rss_sources.php';
    return array_values(array_filter($sources, static function ($source) {
        return is_array($source)
            && !empty($source['enabled'])
            && !empty($source['key'])
            && !empty($source['name'])
            && !empty($source['url'])
            && isAllowedRssUrl($source['url'], $source);
    }));
}

function findRssSource(?string $sourceKey): ?array
{
    foreach (getConfiguredRssSources() as $source) {
        if ($sourceKey === null || $sourceKey === '' || hash_equals((string)$source['key'], (string)$sourceKey)) {
            return $source;
        }
    }
    return null;
}

function isAllowedRssUrl(string $url, ?array $source = null): bool
{
    $parts = parse_url($url);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        return false;
    }
    if ($source !== null && isset($source['url']) && $url !== $source['url']) {
        return false;
    }
    if ($source !== null && !empty($source['allowed_hosts'])) {
        $host = strtolower(rtrim((string)$parts['host'], '.'));
        $allowedHosts = array_map(static fn($value) => strtolower(rtrim((string)$value, '.')), (array)$source['allowed_hosts']);
        return in_array($host, $allowedHosts, true);
    }
    return false;
}

function isAllowedRssArticleUrl(string $url, array $source): bool
{
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
        return false;
    }
    $host = strtolower(rtrim((string)$parts['host'], '.'));
    $allowedHosts = array_map(static fn($value) => strtolower(rtrim((string)$value, '.')), (array)($source['allowed_hosts'] ?? []));
    return in_array($host, $allowedHosts, true);
}

function sanitizeRssText(?string $value, int $maxLength = 500): string
{
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function fetchRssXml(array $source): string
{
    $url = (string)($source['url'] ?? '');
    if (!isAllowedRssUrl($url, $source)) {
        throw new RuntimeException('RSS source is not allowlisted');
    }

    if (function_exists('curl_init')) {
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml;q=0.9'],
            CURLOPT_USERAGENT => 'Machtin-RSS-Reader/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > RSS_MAX_RESPONSE_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!$ok || $status < 200 || $status >= 300 || $error !== '' || $body === '') {
            throw new RuntimeException('RSS fetch failed');
        }
        return $body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 6,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => "Accept: application/rss+xml, application/xml, text/xml;q=0.9\r\nUser-Agent: Machtin-RSS-Reader/1.0\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false || strlen($body) > RSS_MAX_RESPONSE_BYTES) {
        throw new RuntimeException('RSS fetch failed');
    }
    return $body;
}

function normalizeRssItem(SimpleXMLElement $item, array $source): ?array
{
    $title = sanitizeRssText((string)($item->title ?? ''), 220);
    $summary = sanitizeRssText((string)($item->description ?? $item->summary ?? ''), 360);
    $url = trim((string)($item->link ?? ''));
    if ($title === '' || !isAllowedRssArticleUrl($url, $source)) {
        return null;
    }
    $published = trim((string)($item->pubDate ?? $item->published ?? $item->updated ?? ''));
    $timestamp = $published !== '' ? strtotime($published) : false;
    return [
        'source_key' => (string)$source['key'],
        'source_name' => sanitizeRssText((string)$source['name'], 80),
        'attribution' => sanitizeRssText((string)($source['attribution'] ?? $source['name']), 100),
        'title' => $title,
        'summary' => $summary,
        'url' => $url,
        'published_at' => $timestamp ? date(DATE_ATOM, $timestamp) : null,
    ];
}

function parseRssItems(string $xmlString, array $source, int $maxItems = RSS_MAX_ITEMS): array
{
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xmlString)) {
        throw new RuntimeException('RSS DTD is not accepted');
    }
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('XML parser unavailable');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOBLANKS);
        if ($xml === false) {
            throw new RuntimeException('RSS XML is invalid');
        }
        $nodes = isset($xml->channel->item) ? $xml->channel->item : $xml->item;
        $items = [];
        foreach ($nodes as $node) {
            $normalized = normalizeRssItem($node, $source);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
            if (count($items) >= min($maxItems, RSS_MAX_ITEMS)) {
                break;
            }
        }
        return $items;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function getRssCachePath(): string
{
    return dirname(__DIR__) . '/runtime/rss-cache.json';
}

function readRssCache(): ?array
{
    $path = getRssCachePath();
    if (!is_file($path)) return null;
    $decoded = json_decode((string)@file_get_contents($path), true);
    return is_array($decoded) && isset($decoded['fetched_at'], $decoded['items']) ? $decoded : null;
}

function writeRssCache(array $payload): void
{
    $directory = dirname(getRssCachePath());
    if (!is_dir($directory)) @mkdir($directory, 0750, true);
    $temp = $directory . '/rss-cache.' . bin2hex(random_bytes(5)) . '.tmp';
    if (@file_put_contents($temp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
        @rename($temp, getRssCachePath());
    }
}

function getRssNews(?string $sourceKey = null, int $limit = 8): array
{
    $sources = getConfiguredRssSources();
    if ($sourceKey !== null && $sourceKey !== '' && findRssSource($sourceKey) === null) {
        throw new InvalidArgumentException('RSS source is invalid');
    }
    $cache = readRssCache();
    $age = $cache ? max(0, time() - (int)$cache['fetched_at']) : PHP_INT_MAX;
    if ($cache && $age <= RSS_CACHE_TTL) {
        $items = $cache['items'];
        if ($sourceKey) $items = array_values(array_filter($items, static fn($item) => $item['source_key'] === $sourceKey));
        return ['items' => array_slice($items, 0, $limit), 'fetched_at' => date(DATE_ATOM, (int)$cache['fetched_at']), 'from_cache' => true, 'cache_age' => $age, 'source_count' => count($sources)];
    }

    $items = [];
    $successful = 0;
    foreach ($sources as $source) {
        // Luôn xây cache tổng khi cache hết hạn; filter source chỉ áp dụng sau khi fetch.
        try {
            $items = array_merge($items, parseRssItems(fetchRssXml($source), $source, RSS_MAX_ITEMS));
            $successful++;
        } catch (Throwable $error) {
            error_log('RSS source failed: ' . (string)$source['key']);
        }
    }
    usort($items, static fn($a, $b) => strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? '')));
    $now = time();
    // Cache đủ item của nhiều nguồn; limit chỉ áp dụng ở response cuối.
    $payload = ['fetched_at' => $now, 'items' => array_slice($items, 0, RSS_CACHE_MAX_ITEMS), 'successful_sources' => $successful];
    if ($successful > 0) writeRssCache($payload);
    $selected = $sourceKey ? array_values(array_filter($payload['items'], static fn($item) => $item['source_key'] === $sourceKey)) : $payload['items'];
    return ['items' => array_slice($selected, 0, $limit), 'fetched_at' => date(DATE_ATOM, $now), 'from_cache' => false, 'cache_age' => 0, 'source_count' => $successful];
}
