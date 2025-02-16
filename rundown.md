# 📌 Perencanaan Proyek: Clouding Catatan Keuangan

## 📅 Tahapan Pengembangan

### 1️⃣ **Perencanaan & Setup Proyek** (Hari 1-2)
- Menentukan fitur utama:
  - [ ] **Autentikasi pengguna** (Login/Register)
  - [ ] **Dashboard utama** (Ringkasan keuangan)
  - [ ] **Tambah/Edit/Hapus catatan keuangan**
  - [ ] **Filter & pencarian transaksi**
  - [ ] **Laporan keuangan** (bulanan & tahunan)
  - [ ] **Grafik keuangan** (Visualisasi pemasukan & pengeluaran)
  - [ ] **Kategori pengeluaran/pemasukan**
  - [ ] **Dark mode & UI modern**
  - [ ] **Pengaturan Mata Uang (IDR/USD)**
- Menyiapkan lingkungan kerja:
  - [ ] Install PHP & MySQL
  - [ ] Konfigurasi database `database.sql`
  - [ ] Setup Tailwind CSS (CDN atau Build)
  - [ ] Struktur folder dan file dasar

---

### 2️⃣ **Pengembangan Backend (PHP + MySQL)** (Hari 3-6)
- [ ] Membuat **Database & Model**
  - [ ] Tabel `users` (id, nama, email, password, preferensi_kurs)
  - [ ] Tabel `transactions` (id, user_id, kategori, jumlah, kurs, tanggal)
- [ ] Implementasi **Autentikasi Pengguna**
  - [ ] Login & Logout menggunakan `AuthController.php`
  - [ ] Registrasi pengguna baru
  - [ ] Hashing password dengan `password_hash()`
- [ ] CRUD **Catatan Keuangan**
  - [ ] Tambah transaksi (`TransactionController.php`)
  - [ ] Edit transaksi
  - [ ] Hapus transaksi
  - [ ] Menampilkan transaksi berdasarkan user
- [ ] **Pengaturan Mata Uang**
  - [ ] Opsi memilih **IDR atau USD** di halaman pengaturan
  - [ ] Simpan preferensi mata uang ke database
  - [ ] Format tampilan sesuai kurs yang dipilih

---

### 3️⃣ **Pengembangan Frontend (HTML + Tailwind CSS + JS)** (Hari 7-10)
- [ ] **Tampilan Login & Register** (Form responsif)
- [ ] **Dashboard**
  - [ ] Menampilkan saldo & ringkasan transaksi
  - [ ] Menampilkan daftar transaksi
  - [ ] Tombol tambah transaksi
- [ ] **Form Tambah/Edit Transaksi**
  - [ ] Input kategori, jumlah, tanggal
  - [ ] Validasi input sebelum disimpan
- [ ] **Filter & Pencarian Transaksi**
  - [ ] Berdasarkan kategori & rentang waktu
  - [ ] Menggunakan JavaScript untuk update data tanpa reload
- [ ] **Pengaturan Mata Uang**
  - [ ] Pilihan **IDR atau USD**
  - [ ] Simpan preferensi pengguna
  - [ ] Menyesuaikan format tampilan berdasarkan kurs
- [ ] **Fitur Hapus Transaksi**
  - [ ] Tombol hapus di daftar transaksi
  - [ ] Konfirmasi sebelum menghapus data
  - [ ] AJAX untuk hapus tanpa reload
- [ ] **Dark Mode**
  - [ ] Toggle dark mode dengan Tailwind CSS
- [ ] **Grafik Keuangan** (Menggunakan Chart.js)
  - [ ] Grafik pemasukan vs pengeluaran

---

### 4️⃣ **Pengujian & Debugging** (Hari 11-12)
- [ ] **Cek keamanan** (SQL Injection, XSS)
- [ ] **Uji coba fitur utama**
  - [ ] Login/Register berfungsi dengan baik
  - [ ] CRUD transaksi berjalan tanpa error
  - [ ] Dashboard menampilkan data dengan benar
  - [ ] Filter & pencarian transaksi bekerja
  - [ ] Dark mode & UI modern responsif
  - [ ] Perubahan kurs (IDR/USD) diterapkan dengan benar
  - [ ] Hapus transaksi berfungsi dengan AJAX
- [ ] **Testing di beberapa browser**

---

### 5️⃣ **Deploy & Dokumentasi** (Hari 13-14)
- [ ] **Deploy ke hosting**
  - [ ] Konfigurasi database di server
  - [ ] Upload file ke hosting
- [ ] **Buat Dokumentasi**
  - [ ] Cara install & setup proyek
  - [ ] Panduan penggunaan aplikasi
- [ ] **Optimasi UI & UX**
  - [ ] Evaluasi feedback pengguna awal
  - [ ] Perbaikan bug kecil jika ditemukan

---

## 🎯 **Target Akhir**
✔ Aplikasi berbasis web untuk mencatat keuangan dengan fitur lengkap  
✔ UI modern & responsif menggunakan Tailwind CSS  
✔ Backend menggunakan PHP & MySQL  
✔ Grafik keuangan interaktif  
✔ Fitur **mata uang IDR/USD yang bisa diatur**  
✔ Fitur **hapus transaksi dengan AJAX**  
✔ Fitur dark mode untuk kenyamanan pengguna  

---

## 📂 **Struktur Direktori (Tanpa `public/`)**
```bash
clouding-keuangan/
│── assets/              # Aset frontend (CSS, JS, gambar)
│   ├── css/
│   ├── js/
│   ├── images/
│── src/                 # Backend PHP
│   ├── config/          # Koneksi database
│   ├── controllers/     # Logika backend
│   ├── models/          # Model database
│   ├── views/           # Tampilan HTML
│   ├── settings.php     # Halaman pengaturan mata uang
│── storage/             # Penyimpanan file
│── index.php            # Halaman utama
│── login.php            # Halaman login
│── register.php         # Halaman registrasi
│── database.sql         # Struktur database
│── .env                 # Konfigurasi lingkungan
│── README.md            # Dokumentasi proyek
