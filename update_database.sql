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
