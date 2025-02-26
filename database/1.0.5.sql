-- Add security settings table
CREATE TABLE security_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add recaptcha version column
ALTER TABLE security_settings 
ADD COLUMN recaptcha_version ENUM('v2', 'v3') DEFAULT 'v3' 
AFTER setting_key;

-- Insert default reCAPTCHA settings
INSERT INTO security_settings (setting_key, setting_value, is_enabled, recaptcha_version) VALUES
('recaptcha_site_key', '', FALSE, 'v3'),
('recaptcha_secret_key', '', FALSE, 'v3');

-- Add remember me token columns to users table
ALTER TABLE users 
ADD COLUMN remember_token VARCHAR(64) NULL,
ADD COLUMN token_expires DATETIME NULL;

-- Create index for faster token lookups
CREATE INDEX idx_remember_token ON users(remember_token);

-- Update database version
UPDATE database_version SET version = '1.0.5' WHERE id = 1;
