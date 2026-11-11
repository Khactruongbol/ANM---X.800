-- Phase 6: audit log cho các sự kiện bảo mật và nghiệp vụ quan trọng.
-- Chạy migration này một lần trên database machtin_db.
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NULL,
    role VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(100) NULL,
    target_id VARCHAR(100) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent TEXT NULL,
    detail TEXT NULL,
    previous_hash CHAR(64) NULL,
    current_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_action (action),
    KEY idx_audit_user_id (user_id),
    KEY idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
