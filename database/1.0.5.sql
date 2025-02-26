-- Add security settings table
CREATE TABLE security_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE security_settings 
ADD COLUMN recaptcha_version ENUM('v2', 'v3') DEFAULT 'v3' 
AFTER setting_key;

-- Insert default reCAPTCHA settings with disabled state
INSERT INTO security_settings (setting_key, setting_value, is_enabled) VALUES
('recaptcha_site_key', '', FALSE),
('recaptcha_secret_key', '', FALSE);

-- Initial state: disabled
UPDATE security_settings SET is_enabled = FALSE WHERE setting_key IN ('recaptcha_site_key', 'recaptcha_secret_key');

-- Update default values
UPDATE security_settings 
SET recaptcha_version = 'v3' 
WHERE setting_key IN ('recaptcha_site_key', 'recaptcha_secret_key');

-- Update database version
UPDATE database_version SET version = '1.0.5' WHERE id = 1;
