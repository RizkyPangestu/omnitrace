# OmniTrace

Aplikasi web untuk mengelola pengajuan riset berbasis sumber publik. Halaman HTML dan CSS mengikuti prototipe OMNITRACE BIFEST. PHP menangani formulir, autentikasi, otorisasi per kasus, dan penyimpanan SQLite. Folder `views/` berisi template `.html` yang dirender oleh PHP. Dokumen root server adalah `public/`.

## Jalankan untuk demo lokal

Gunakan PHP 8.2 atau lebih baru dengan PDO SQLite dan dukungan Argon2id. Periksa instalasi dari folder proyek.

```bash
php -r 'var_dump(PDO::getAvailableDrivers(), password_algos());'
php bin/omnitrace.php init-db
php bin/omnitrace.php create-staff admin admin@example.org "Demo Admin"
php bin/omnitrace.php create-staff analyst analyst@example.org "Demo Analyst"
OMNITRACE_LOCAL=1 php -d post_max_size=3M -d upload_max_filesize=2M -S 127.0.0.1:5000 -t public dev-router.php
```

Buka [OmniTrace](http://127.0.0.1:5000). CLI meminta kata sandi akun staf secara interaktif. Buat akun klien melalui **Create an account**. Gunakan profil browser terpisah atau logout sebelum berpindah peran. Direktori `var/` menyimpan database dan sesi di luar dokumen root.

### Windows dengan XAMPP

Dari PowerShell, gunakan PHP XAMPP yang mendukung PDO SQLite dan Argon2id. Sesuaikan path sesuai instalasi. Aktifkan ekstensi PDO SQLite di `php.ini` yang dipakai CLI jika perlu.

```powershell
$php = "C:\xampp\php\php.exe"
& $php -r 'var_dump(PDO::getAvailableDrivers(), password_algos());'
& $php bin/omnitrace.php init-db
$admin = Get-Credential -UserName "admin@example.org" -Message "Tentukan password akun admin OmniTrace"
$admin.GetNetworkCredential().Password | & $php bin/omnitrace.php create-staff admin admin@example.org "Demo Admin" --password-stdin
$analyst = Get-Credential -UserName "analyst@example.org" -Message "Tentukan password akun analis OmniTrace"
$analyst.GetNetworkCredential().Password | & $php bin/omnitrace.php create-staff analyst analyst@example.org "Demo Analyst" --password-stdin
$env:OMNITRACE_LOCAL = "1"
& $php -d post_max_size=3M -d upload_max_filesize=2M -S 127.0.0.1:5000 -t public dev-router.php
```

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

Gunakan web server dengan PHP dan TLS. Tetapkan `public/` sebagai dokumen root, layani aset dari `/static/`, dan teruskan rute aplikasi ke `public/index.php`. Pada Apache, `FallbackResource /index.php` di virtual host dapat menangani rute tersebut. Pada Nginx, gunakan `try_files $uri /index.php?$query_string` dan arahkan eksekusi PHP ke front controller di PHP-FPM. Pastikan web server atau proxy tepercaya menetapkan variabel server `HTTPS` untuk koneksi TLS, serta `REMOTE_ADDR` dari IP klien yang telah diverifikasi.

Hapus `OMNITRACE_LOCAL` dari lingkungan deployment, set `OMNITRACE_HOSTS` ke domain aplikasi, dan alihkan HTTP ke HTTPS di web server. Tetapkan `post_max_size=3M` dan `upload_max_filesize=2M` di konfigurasi PHP. Gunakan direktori privat yang dapat ditulis oleh akun layanan untuk database dan sesi. `OMNITRACE_DATA_DIR` dapat menunjuk ke direktori data privat. `OMNITRACE_DB` dapat menunjuk ke berkas SQLite tertentu di luar dokumen root.

Jika beralih dari versi Flask dengan database lama, cadangkan database dahulu, tetapkan `OMNITRACE_DB` ke berkas tersebut, lalu jalankan `php bin/omnitrace.php init-db`. Skema yang sama mempertahankan akun, kasus, bukti, dan laporan. Akun lama perlu mereset kata sandi satu kali untuk beralih dari scrypt ke Argon2id. Pengguna masuk ulang setelah migrasi sesi.

Aplikasi menyimpan URL sumber sebagai teks dan pemeriksaan lampiran menggunakan batas ukuran serta awalan berkas yang diizinkan. Penyebaran dengan data nyata memerlukan pengaturan izin penyimpanan, pencadangan, dan pemindaian berkas sesuai kebutuhan. Gunakan data fiktif untuk demo dan pertukaran kode.

## Laporan tugas

- [Dokumentasi OmniTrace](Laporan/01-Dokumentasi-OmniTrace.docx)
- [Draf code review kelompok pasangan](Laporan/02-Draf-Code-Review-Kelompok-Pasangan.docx). Temuan dan bukti per temuan membutuhkan source code kelompok pasangan.
