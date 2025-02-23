-- 1. Buat tabel storage_types terlebih dahulu
CREATE TABLE IF NOT EXISTS storage_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(50) NOT NULL,
    jenis ENUM('cash', 'bank', 'ewallet') NOT NULL,
    icon VARCHAR(50) NOT NULL
);

-- 2. Masukkan data default untuk metode penyimpanan
INSERT INTO storage_types (nama, jenis, icon) VALUES 
('Cash/Fisik', 'cash', 'fa-money-bill-wave'),
('BCA', 'bank', 'fa-university'),
('Mandiri', 'bank', 'fa-university'),
('BRI', 'bank', 'fa-university'),
('BNI', 'bank', 'fa-university'),
('GoPay', 'ewallet', 'fa-wallet'),
('OVO', 'ewallet', 'fa-wallet'),
('DANA', 'ewallet', 'fa-wallet'),
('ShopeePay', 'ewallet', 'fa-wallet');

-- 3. Tambahkan kolom storage_type_id
ALTER TABLE transactions 
ADD COLUMN storage_type_id INT NOT NULL;

-- 4. Set default value untuk data yang sudah ada
UPDATE transactions 
SET storage_type_id = (
    SELECT id FROM storage_types WHERE nama = 'Cash/Fisik' LIMIT 1
);

-- 5. Tambahkan foreign key constraint
ALTER TABLE transactions
ADD CONSTRAINT transactions_storage_type_id_foreign
FOREIGN KEY (storage_type_id) REFERENCES storage_types(id);

-- 6. Update database untuk menyimpan remember token
ALTER TABLE users 
ADD COLUMN remember_token VARCHAR(64) NULL,
ADD COLUMN token_expires DATETIME NULL;

-- Add admin column to users table
ALTER TABLE users 
ADD COLUMN is_admin BOOLEAN DEFAULT FALSE;

-- Add last_activity column to track online status
ALTER TABLE users 
ADD COLUMN last_activity TIMESTAMP NULL;

-- Create default admin user
INSERT INTO users (nama, email, password, is_admin) 
VALUES ('Admin', 'admin@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE);
-- Note: password is 'admin'

-- 7. Buat tabel financial_targets untuk menyimpan target keuangan
CREATE TABLE financial_targets_new (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    target_type ENUM('pemasukan', 'pengeluaran', 'saldo') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    kurs ENUM('IDR', 'USD') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    period_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_target (user_id, target_type, period_type, start_date, end_date)
);

-- 8. Salin data dari tabel lama ke tabel baru (jika ada)
INSERT INTO financial_targets_new 
SELECT id, user_id, target_type, amount, kurs, is_active, 
       'monthly' as period_type, -- default ke monthly untuk data lama
       CURRENT_DATE as start_date, 
       LAST_DAY(CURRENT_DATE) as end_date,
       created_at
FROM financial_targets;

-- 9. Drop tabel lama
DROP TABLE financial_targets;

-- 10. Rename tabel baru
RENAME TABLE financial_targets_new TO financial_targets;

-- Drop unique constraint agar bisa set multiple target
ALTER TABLE financial_targets 
DROP INDEX unique_target;

-- Buat index baru yang lebih sesuai
CREATE INDEX idx_financial_targets ON financial_targets (user_id, target_type, period_type);
