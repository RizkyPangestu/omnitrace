# OmniTrace

Aplikasi web pengajuan investigasi berbasis sumber publik. Halaman HTML/CSS mengikuti prototipe OMNITRACE BIFEST. PHP menangani formulir, autentikasi, dan otorisasi per kasus, dengan MySQL dan InnoDB untuk penyimpanan. Folder `views/` berisi template HTML. Dokumen root server adalah `public/`.

## Siapkan database MySQL

Gunakan PHP 8.2 atau lebih baru dengan `pdo_mysql` dan dukungan Argon2id, serta MySQL 8.0.16+ atau MariaDB 10.4+ untuk XAMPP. Mulai layanan MySQL di XAMPP, lalu jalankan perintah SQL berikut di phpMyAdmin atau klien MySQL dengan akun administrator. Ganti contoh kata sandi sebelum menjalankan SQL.

```sql
CREATE DATABASE omnitrace CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
CREATE USER 'omnitrace'@'127.0.0.1' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KAMU';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, REFERENCES
ON omnitrace.* TO 'omnitrace'@'127.0.0.1';
```

PHP membaca konfigurasi dari environment. Isi nama database, user, dan port sesuai instalasi bila berbeda dari nilai bawaan.

| Variabel | Nilai bawaan |
| --- | --- |
| `OMNITRACE_DB_HOST` | `127.0.0.1` |
| `OMNITRACE_DB_PORT` | `3306` |
| `OMNITRACE_DB_NAME` | `omnitrace` |
| `OMNITRACE_DB_USER` | `omnitrace` |
| `OMNITRACE_DB_PASSWORD` | Kata sandi akun database yang kamu buat |
| `OMNITRACE_DB_SSL_CA` | Opsional. Path sertifikat CA untuk koneksi MySQL yang memakai TLS dan verifikasi server |

## Jalankan untuk demo lokal

Dari folder proyek, masukkan password MySQL melalui prompt dan jalankan setup.

```bash
php -r 'var_dump(PDO::getAvailableDrivers(), password_algos());'
read -rs -p "Password MySQL: " OMNITRACE_DB_PASSWORD
printf '\n'
export OMNITRACE_DB_PASSWORD
php bin/omnitrace.php init-db
php bin/omnitrace.php create-staff admin admin@example.org "Demo Admin"
php bin/omnitrace.php create-staff analyst analyst@example.org "Demo Analyst"
OMNITRACE_LOCAL=1 php -d post_max_size=3M -d upload_max_filesize=2M -S 127.0.0.1:5000 -t public dev-router.php
```

`init-db` membuat tabel dari [schema.sql](schema.sql) di database yang telah dibuat. Kalau kamu mengimpor `schema.sql` langsung lewat tab **Import** pada database `omnitrace` di phpMyAdmin, kamu bisa lanjut ke pembuatan akun staf.

### Windows dengan XAMPP

Dari PowerShell, gunakan PHP XAMPP. Sesuaikan path sesuai instalasi dan aktifkan ekstensi `pdo_mysql` di `php.ini` CLI jika perlu.

```powershell
$php = "C:\xampp\php\php.exe"
& $php -r 'var_dump(PDO::getAvailableDrivers(), password_algos());'
$db = Get-Credential -UserName "omnitrace" -Message "Masukkan password akun MySQL OmniTrace"
$env:OMNITRACE_DB_USER = $db.UserName
$env:OMNITRACE_DB_PASSWORD = $db.GetNetworkCredential().Password
& $php bin/omnitrace.php init-db
$admin = Get-Credential -UserName "admin@example.org" -Message "Tentukan password akun admin OmniTrace"
$admin.GetNetworkCredential().Password | & $php bin/omnitrace.php create-staff admin admin@example.org "Demo Admin" --password-stdin
$analyst = Get-Credential -UserName "analyst@example.org" -Message "Tentukan password akun analis OmniTrace"
$analyst.GetNetworkCredential().Password | & $php bin/omnitrace.php create-staff analyst analyst@example.org "Demo Analyst" --password-stdin
$env:OMNITRACE_LOCAL = "1"
& $php -d post_max_size=3M -d upload_max_filesize=2M -S 127.0.0.1:5000 -t public dev-router.php
```

Buka [OmniTrace](http://127.0.0.1:5000). Buat akun klien melalui **Create an account**. Gunakan profil browser terpisah atau logout sebelum berpindah peran. PHP menyimpan file sesi di `var/sessions/`, di luar dokumen root.

## Impor data dari versi sebelumnya

Untuk database yang pernah dipakai di versi SQLite, cadangkan berkas lama lalu isi konfigurasi MySQL untuk database tujuan. Inisialisasi tabel dan impor sebelum membuat akun atau membuka aplikasi. Impor memerlukan `pdo_sqlite` hanya selama perintah ini.

```bash
php bin/omnitrace.php init-db
php bin/omnitrace.php import-sqlite "path/ke/omni.sqlite3"
```

Impor memindahkan akun, kasus, bukti dan lampiran, laporan, serta riwayat ke database MySQL kosong dalam satu transaksi. Akun dengan hash Argon2id bisa masuk memakai kata sandinya. Pengguna masuk ulang dan meminta kode reset baru jika diperlukan. Akun yang masih memiliki hash scrypt dari versi awal perlu reset kata sandi sekali.

## Alur demo

1. Klien masuk, memilih **Request investigation**, mengisi tujuan, mengonfirmasi kewenangan, lalu mengirim brief.
2. Admin memilih **Approve and assign** untuk menugaskan analis. Admin juga dapat menolak dengan alasan.
3. Analis menambahkan catatan bukti dan lampiran jika diperlukan, lalu memilih **Submit for review** pada laporan.
4. Admin meninjau laporan dan memilih **Publish report** atau **Request revision**. Analis dapat memperbaiki dan mengirim ulang laporan.
5. Klien pemilik membuka kasusnya dan mengunduh laporan terbit.

Untuk reset kata sandi, operator memverifikasi pemilik akun lalu menjalankan perintah berikut. Bagikan kode melalui kanal privat. Pengguna memasukkan kode pada **Forgot password?** dalam 15 menit.

```bash
php bin/omnitrace.php issue-reset client@example.org
```

## Penggunaan melalui HTTPS

Gunakan web server dengan PHP dan TLS. Tetapkan `public/` sebagai dokumen root dan teruskan rute aplikasi ke `public/index.php`. Pada Apache, gunakan `FallbackResource /index.php` di virtual host. Pada Nginx, gunakan `try_files $uri /index.php?$query_string` dan arahkan front controller ke PHP-FPM. Pastikan web server atau proxy tepercaya menetapkan `HTTPS` untuk koneksi TLS dan `REMOTE_ADDR` dari IP klien yang telah diverifikasi.

Jalankan deployment tanpa mode lokal, set `OMNITRACE_HOSTS` ke domain aplikasi, dan alihkan HTTP ke HTTPS. Tetapkan `post_max_size=3M` dan `upload_max_filesize=2M` di konfigurasi PHP. Gunakan `OMNITRACE_DATA_DIR` jika perlu memindahkan file sesi ke direktori privat. Akun database untuk menjalankan aplikasi memerlukan hak `SELECT`, `INSERT`, `UPDATE`, dan `DELETE`.

Aplikasi menyimpan URL sumber sebagai teks dan memeriksa batas ukuran serta awalan berkas yang diizinkan untuk lampiran. Gunakan data fiktif untuk demo dan pertukaran kode.

## Laporan tugas

- [Dokumentasi OmniTrace](Laporan/01-Dokumentasi-OmniTrace.docx)
- [Draf code review kelompok pasangan](Laporan/02-Draf-Code-Review-Kelompok-Pasangan.docx). Temuan dan bukti per temuan membutuhkan source code kelompok pasangan.
