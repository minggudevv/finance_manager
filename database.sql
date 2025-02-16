CREATE DATABASE IF NOT EXISTS clouding_keuangan;
USE clouding_keuangan;

-- Create users table with remember me token columns
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    preferensi_kurs ENUM('IDR', 'USD') DEFAULT 'IDR',
    remember_token VARCHAR(64) NULL,
    token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create storage types table first since it's referenced by transactions
CREATE TABLE storage_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(50) NOT NULL,
    jenis ENUM('cash', 'bank', 'ewallet') NOT NULL,
    icon VARCHAR(50) NOT NULL
);

-- Create transactions table with storage_type_id
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    storage_type_id INT NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    kurs ENUM('IDR', 'USD') NOT NULL,
    jenis ENUM('pemasukan', 'pengeluaran') NOT NULL,
    tanggal DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (storage_type_id) REFERENCES storage_types(id)
);

-- Insert default storage types
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

UPDATE transactions 
SET storage_type_id = (
    SELECT id FROM storage_types WHERE nama = 'Cash/Fisik' LIMIT 1
);

ALTER TABLE transactions
ADD CONSTRAINT transactions_storage_type_id_foreign
FOREIGN KEY (storage_type_id) REFERENCES storage_types(id);

ALTER TABLE users 
ADD COLUMN remember_token VARCHAR(64) NULL,
ADD COLUMN token_expires DATETIME NULL;

-- Add indexes for better performance
CREATE INDEX idx_transactions_user_id ON transactions(user_id);
CREATE INDEX idx_transactions_storage_type_id ON transactions(storage_type_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_remember_token ON users(remember_token);
