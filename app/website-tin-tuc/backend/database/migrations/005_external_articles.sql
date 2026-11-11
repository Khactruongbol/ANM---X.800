-- Phase 12: metadata RSS tách khỏi bài viết nội bộ.
CREATE TABLE IF NOT EXISTS external_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_key VARCHAR(80) NOT NULL,
    source_name VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    original_url VARCHAR(2048) NOT NULL,
    canonical_url VARCHAR(2048) NULL,
    published_at DATETIME NULL,
    fetched_at DATETIME NOT NULL,
    content_hash CHAR(64) NOT NULL,
    status ENUM('pending', 'approved', 'hidden', 'expired') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_external_source_hash (source_key, content_hash),
    KEY idx_external_status_published (status, published_at),
    KEY idx_external_source_fetched (source_key, fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
