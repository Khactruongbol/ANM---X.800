-- Phase 11: rate limit cho register, comment, upload va cac luong co the spam.
CREATE TABLE IF NOT EXISTS action_rate_limits (
    action_key VARCHAR(80) NOT NULL,
    subject_key VARCHAR(190) NOT NULL,
    window_started DATETIME NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (action_key, subject_key, window_started),
    KEY idx_action_rate_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
