# SCHEDULIN — Panduan Instalasi

## Persyaratan
- PHP 7.4+ (direkomendasikan PHP 8.1+)
- MySQL 5.7+ atau MariaDB 10.3+
- Apache dengan mod_rewrite aktif (XAMPP/Laragon)
- Browser modern (Chrome, Firefox, Edge)

## Langkah Instalasi

### 1. Salin project ke server lokal
```
Salin folder `schedulin/` ke:
XAMPP  → C:/xampp/htdocs/schedulin/
Laragon → C:/laragon/www/schedulin/
```

### 2. Buat database
Buka phpMyAdmin atau MySQL CLI, jalankan:
```sql
SOURCE /path/to/schedulin/schema.sql;
```
Atau copy-paste isi `schema.sql` ke query phpMyAdmin.

### 3. Konfigurasi database
Edit file `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // username MySQL kamu
define('DB_PASS', '');           // password MySQL kamu
define('DB_NAME', 'schedulin_db');
define('APP_URL', 'http://localhost/schedulin');
```

### 4. Aktifkan mod_rewrite (XAMPP)
- Buka `C:/xampp/apache/conf/httpd.conf`
- Pastikan baris ini tidak dikomentari:
  `LoadModule rewrite_module modules/mod_rewrite.so`
- Cari `AllowOverride None` dan ubah ke `AllowOverride All`
- Restart Apache

### 5. Akses aplikasi
Buka browser: `http://localhost/schedulin`

---

## Struktur Folder
```
schedulin/
├── index.php              ← Halaman utama (jadwal planner)
├── schema.sql             ← Script database
├── .htaccess              ← Konfigurasi Apache
│
├── includes/
│   ├── config.php         ← Konfigurasi DB & konstanta
│   ├── db.php             ← Database connection class
│   └── functions.php      ← Helper functions
│
├── pages/
│   ├── login.php          ← Halaman login
│   ├── register.php       ← Halaman registrasi
│   └── logout.php         ← Logout handler
│
├── api/
│   └── matkul.php         ← REST API endpoint (CRUD matkul)
│
└── assets/
    ├── css/
    │   └── style.css      ← Stylesheet utama
    └── js/
        └── app.js         ← Logic JS (drag-drop, conflict, auto-schedule)
```

---

## Fitur
- ✅ Registrasi & Login mahasiswa
- ✅ Input matkul manual (nama, kelas, SKS, dosen, hari, jam)
- ✅ Drag & drop kartu matkul antar hari
- ✅ Conflict detection real-time (warna merah + warning)
- ✅ Auto-Schedule otomatis (algoritma greedy)
- ✅ Import dari file Excel (.xlsx)
- ✅ Download template Excel
- ✅ Export jadwal ke Excel
- ✅ Simpan jadwal ke database (persist)
- ✅ Session timeout otomatis

## Teknologi
- Backend : PHP 8+ (tanpa framework)
- Database: MySQL / MariaDB
- Frontend: HTML, CSS, Vanilla JavaScript
- Library : SheetJS (Excel), Tabler Icons
