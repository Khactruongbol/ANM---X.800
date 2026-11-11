<?php

/**
 * Allowlist RSS lịch sử. Chế độ local-only hiện tắt toàn bộ đồng bộ nguồn ngoài.
 */
return [
    [
        'key' => 'vnexpress-home',
        'name' => 'VnExpress',
        'url' => 'https://vnexpress.net/rss/tin-moi-nhat.rss',
        'allowed_hosts' => ['vnexpress.net', 'www.vnexpress.net'],
        'attribution' => 'Theo VnExpress',
        'enabled' => false,
    ],
    [
        'key' => 'dantri-home',
        'name' => 'Dân trí',
        'url' => 'https://dantri.com.vn/rss/home.rss',
        'allowed_hosts' => ['dantri.com.vn', 'www.dantri.com.vn'],
        'attribution' => 'Theo Dân trí',
        'enabled' => false,
    ],
    [
        'key' => 'thanhnien-home',
        'name' => 'Thanh Niên',
        'url' => 'https://thanhnien.vn/rss/home.rss',
        'allowed_hosts' => ['thanhnien.vn', 'www.thanhnien.vn'],
        'attribution' => 'Theo Báo Thanh Niên',
        'enabled' => false,
    ],
];
