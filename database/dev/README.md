# Development Database

Folder ini berisi file-file SQL khusus untuk proses development. File-file ini **TIDAK BOLEH** digunakan di production environment.

## Struktur Folder

```
database/dev/
├── schema.sql           # Skema dasar database development
├── *.*.*.sql           # File migrasi untuk development
└── README.md           # Dokumentasi ini
```

## Penggunaan

### Schema.sql
- File ini berisi struktur database awal untuk development
- Semua tabel memiliki suffix `_dev` untuk membedakan dengan tabel production
- Berisi data testing awal yang diperlukan untuk development
- File ini akan dijalankan saat pertama kali menginisialisasi database development

### File Migrasi Development
- Format nama file: `<major>.<minor>.<patch>.sql` (contoh: `1.0.1.sql`)
- Berisi perubahan struktur atau data untuk keperluan testing
- Dijalankan sesuai urutan versi
- Hanya berlaku untuk database development

## Cara Menjalankan

Untuk menjalankan migrasi development:
```powershell
php migrate-dev.php
```

## Perbedaan dengan Database Production

1. Semua tabel menggunakan suffix `_dev`
2. Berisi data testing yang tidak akan ada di production
3. Bisa direset kapan saja tanpa mempengaruhi data production
4. Versi database development di-track terpisah di tabel `database_version_dev`

## Panduan Development

1. Selalu test perubahan di database development terlebih dahulu
2. Pastikan tidak mencampur file migrasi development dengan production
3. Jangan menggunakan data sensitif di database development
4. File migrasi development bisa lebih bebas diubah dibanding production

## Keamanan

- Pastikan file-file di folder ini tidak ter-deploy ke production server
- Jangan menggunakan kredensial production di database development
- Data testing harus menggunakan data dummy, bukan data user asli
- Password di database development harus berbeda dengan production

## Tips
- Gunakan `migrate-dev.php` untuk testing fitur database baru
- Reset database development sesering mungkin untuk memastikan migrasi berjalan benar
- Dokumentasikan setiap perubahan di file migrasi development
- Selalu backup schema.sql sebelum melakukan perubahan besar
