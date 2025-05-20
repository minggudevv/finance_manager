-- Check and create security_settings tableif not exists
CREATE TABLE IF NOT EXISTS security_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    recaptcha_version ENUM('v2', 'v3') DEFAULT 'v3',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Check if recaptcha settings exist, if not insert them
INSERT IGNORE INTO security_settings (setting_key, setting_value, is_enabled, recaptcha_version) 
VALUES 
('recaptcha_site_key', '', FALSE, 'v3'),
('recaptcha_secret_key', '', FALSE, 'v3');

-- Add remember me token columns to users table if they don't exist
SET @dbname = DATABASE();
SET @tablename = "users";
SET @columnname = "remember_token";
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = @columnname
    ) > 0,
    "SELECT 1",
    "ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add token_expires column if it doesn't exist
SET @columnname = "token_expires";
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = @columnname
    ) > 0,
    "SELECT 1",
    "ALTER TABLE users ADD COLUMN token_expires DATETIME NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create index for faster token lookups if it doesn't exist
SET @indexname = "idx_remember_token";
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND INDEX_NAME = @indexname
    ) > 0,
    "SELECT 1",
    "CREATE INDEX idx_remember_token ON users(remember_token)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
UPDATE database_version SET version = '1.0.5' WHERE id = 1;
