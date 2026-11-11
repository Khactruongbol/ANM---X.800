-- Phase 5: rate limit đăng nhập.
-- Chạy migration này một lần trên database machtin_db.
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account VARCHAR(255) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    failed_count INT NOT NULL DEFAULT 0,
    first_failed_at DATETIME NOT NULL,
    last_failed_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    UNIQUE KEY uniq_account_ip (account, ip_address),
    KEY idx_blocked_until (blocked_until),
    KEY idx_last_failed_at (last_failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
