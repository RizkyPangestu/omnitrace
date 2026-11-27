# Panduan fungsi OmniTrace

OmniTrace adalah aplikasi untuk mengajukan investigasi, menugaskan analis, mencatat bukti, dan menerbitkan laporan. Tampilan dibuat dengan HTML/CSS. PHP memeriksa formulir dan hak akses, lalu membaca atau menyimpan data ke MySQL. Tautan pada kolom **Kode** membuka bagian yang dijelaskan pada versi MySQL `47bf9a7`.

## Alur yang bisa diikuti saat demo

| Saat pengguna melakukan ini | Bagian aplikasi yang bekerja | Kode |
| --- | --- | --- |
| Klien masuk | Formulir login mengirim email dan kata sandi. PHP membatasi percobaan, memeriksa kata sandi, lalu membuat sesi agar pengguna tetap dikenali setelah berpindah halaman. | [`views/auth.html` baris 20](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/auth.html#L20), [`src/accounts.php` baris 33](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L33), [`src/security.php` baris 220](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L220) |
| Klien mengajukan kasus | PHP memeriksa paket, layanan, isi formulir, dan persetujuan. Kasus baru disimpan dengan status `submitted` dan muncul di dashboard klien. | [`views/new_case.html` baris 10](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/new_case.html#L10), [`src/cases.php` baris 71](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L71) |
| Admin menugaskan analis | Admin membuka kasus dan memilih analis. PHP memeriksa bahwa kasus masih `submitted`, menyimpan penugasan, lalu mengubah status menjadi `assigned`. | [`views/case.html` baris 35](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L35), [`src/cases.php` baris 136–142](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L136-L142) |
| Analis menyimpan bukti dan laporan | Analis yang ditugaskan dapat menambahkan catatan sumber dan lampiran, menyimpan draf, atau mengirim laporan. Pengiriman mengubah status menjadi `in_review`. | [`views/case.html` baris 78](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L78), [`src/cases.php` baris 193](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L193), [`src/cases.php` baris 253](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L253) |
| Admin meninjau laporan | Admin bisa meminta revisi sehingga status menjadi `revisions`, atau menerbitkan laporan sehingga status menjadi `published`. Analis bisa mengirim ulang setelah revisi. | [`views/case.html` baris 57](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L57), [`src/cases.php` baris 147–154](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L147-L154) |
| Klien mengunduh laporan | PHP memeriksa bahwa kasus tersebut milik klien dan laporannya sudah terbit, lalu mengirim laporan dalam format TXT. | [`views/case.html` baris 120](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L120), [`src/cases.php` baris 283](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L283) |

Admin juga dapat menolak pengajuan sebelum menugaskan analis. Alasan penolakan disimpan bersama status `rejected` dan terlihat oleh klien pemilik kasus. [`src/cases.php` baris 143–146](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L143-L146), [`views/case.html` baris 19–22](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L19-L22)

## Rute dan halaman

Rute adalah alamat halaman seperti `/login` atau `/workspace`. Permintaan `GET` biasanya membuka halaman atau unduhan. Formulir yang mengubah data menggunakan `POST` dan membawa token CSRF untuk diperiksa oleh PHP. [`public/index.php` baris 30–89](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/public/index.php#L30-L89), [`public/index.php` baris 93–107](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/public/index.php#L93-L107)

| File atau fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `dev-router.php` | Saat dijalankan dengan server pengembangan PHP, file ini melayani CSS yang terdaftar dan meneruskan rute lain ke `public/index.php`. | [`dev-router.php` baris 4–9](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/dev-router.php#L4-L9) |
| `public/index.php` bagian awal permintaan | PHP memasang header keamanan, memeriksa host dan HTTPS, membatasi ukuran permintaan hingga 3 MB, membuka sesi, memvalidasi CSRF untuk `POST`, dan mencari pengguna yang masuk sebelum menjalankan rute. | [`public/index.php` baris 93–107](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/public/index.php#L93-L107) |
| `allow_methods()` | Memastikan setiap rute menerima metode yang sesuai. Contohnya logout memakai `POST`. Metode yang tidak sesuai mendapat respons 405. | [`public/index.php` baris 13](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/public/index.php#L13) |
| `dispatch()` | Mencocokkan alamat URL dengan fungsi yang menjalankan tindakan. Rute `/login` memanggil `login()` dan rute `/workspace` memanggil `dashboard()`. | [`public/index.php` baris 21](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/public/index.php#L21) |
| `public/index.php` bagian penanganan kesalahan | Menampilkan pesan yang sesuai dengan kode HTTP. Untuk kesalahan internal, detail dicatat di log server dan pengguna mendapat pesan umum. | [`public/index.php` baris 108–115](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/public/index.php#L108-L115) |

## Akun di src/accounts.php

| Fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `submitted_password()` | Memastikan kata sandi dan konfirmasinya sama, lalu meminta `hash_password()` mengubahnya menjadi hash untuk disimpan. | [`src/accounts.php` baris 4](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L4) |
| `create_account()` | Menyimpan akun baru dengan peran yang diberikan. Kalau email sudah terdaftar, fungsi mengembalikan `false`. | [`src/accounts.php` baris 13](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L13) |
| `replace_password()` | Menyimpan hash kata sandi yang baru serta menghapus kode reset dan sesi login lama milik akun tersebut. | [`src/accounts.php` baris 26](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L26) |
| `login()` | Membuka formulir masuk atau memproses isinya. Saat formulir dikirim, fungsi membatasi percobaan, mencari akun, memeriksa kata sandi, dan memulai sesi jika cocok. | [`src/accounts.php` baris 33](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L33) |
| `register()` | Membuka formulir pendaftaran atau membuat akun dengan peran `client` setelah memeriksa email, nama, dan kata sandi. | [`src/accounts.php` baris 56](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L56) |
| `reset_password()` | Memeriksa kode reset yang masih berlaku, mengganti kata sandi, mencabut sesi lama, dan meminta pengguna masuk lagi. | [`src/accounts.php` baris 75](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L75) |
| `logout()` | Menghapus token login aktif, mengganti sesi PHP, dan mengarahkan pengguna ke halaman login. | [`src/accounts.php` baris 106](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L106) |
| `profile()` | Menampilkan profil atau menyimpan nama dan organisasi untuk akun yang sedang masuk. | [`src/accounts.php` baris 114](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L114) |
| `change_password()` | Memeriksa kata sandi saat ini sebelum menyimpan hash baru dan mengakhiri sesi lama. | [`src/accounts.php` baris 128](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L128) |

## Kasus dan laporan di src/cases.php

Di awal file ada daftar paket dan layanan, label status, judul dashboard per peran, serta jenis dan ukuran lampiran yang diizinkan. Nilai ini membuat formulir dan PHP memakai aturan yang sama. [`src/cases.php` baris 4–20](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L4-L20)

| Fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `case_for()` | Mencari kasus yang boleh diakses. Klien hanya mendapat kasus miliknya, analis hanya mendapat kasus yang ditugaskan kepadanya, dan admin dapat membuka semua kasus. Saat diperlukan, fungsi mengunci baris untuk perubahan dalam transaksi. | [`src/cases.php` baris 22](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L22) |
| `case_history()` | Mencatat tindakan seperti penugasan atau publikasi, lengkap dengan ID kasus dan akun yang melakukannya. | [`src/cases.php` baris 44](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L44) |
| `dashboard()` | Mengambil daftar kasus sesuai peran dan menghitung kasus terbuka serta laporan terbit untuk ditampilkan. | [`src/cases.php` baris 49](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L49) |
| `new_case()` | Menampilkan formulir klien atau membuat kasus baru setelah paket, layanan, dan persetujuan diperiksa. | [`src/cases.php` baris 71](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L71) |
| `case_detail()` | Menyiapkan detail dan riwayat kasus untuk halaman. Bukti disiapkan untuk staf yang berhak. Klien melihat laporan setelah diterbitkan. | [`src/cases.php` baris 105](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L105) |
| `decide_case()` | Menjalankan keputusan admin untuk menugaskan analis, menolak pengajuan, meminta revisi, atau menerbitkan laporan sesuai status saat ini. | [`src/cases.php` baris 129](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L129) |
| `uploaded_attachment()` | Membaca lampiran opsional. PHP memeriksa apakah file berasal dari unggahan, berukuran paling banyak 2 MB, dan memiliki awalan yang cocok dengan PDF, PNG, atau JPEG. | [`src/cases.php` baris 164](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L164) |
| `add_evidence()` | Memeriksa penugasan analis, status kasus, alamat sumber, dan waktu akses sebelum menyimpan bukti serta lampiran opsional. | [`src/cases.php` baris 193](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L193) |
| `download_file()` | Mengirim isi berkas dengan nama dan tipe yang sesuai serta menandainya sebagai unduhan. | [`src/cases.php` baris 227](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L227) |
| `download_evidence()` | Memastikan akun adalah admin atau analis yang ditugaskan dan memastikan lampiran benar-benar milik kasus tersebut sebelum mengirim berkas. | [`src/cases.php` baris 238](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L238) |
| `save_report()` | Menyimpan draf laporan atau mengirimnya untuk ditinjau. Pengiriman juga mengubah status kasus dan menambah riwayat. | [`src/cases.php` baris 253](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L253) |
| `download_report()` | Memeriksa hak akses dan status publikasi, lalu menyiapkan ringkasan, temuan, dan batasan laporan sebagai file TXT. | [`src/cases.php` baris 283](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/cases.php#L283) |

## Keamanan di src/security.php

### Sesi dan hak akses

| Fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `open_session()` | Memakai `session_start()` untuk membuka sesi PHP di direktori privat. Cookie memakai `HttpOnly` dan `SameSite=Lax`. Pada deployment HTTPS, cookie juga memakai `Secure`. | [`src/security.php` baris 143](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L143) |
| `random_token()` | Memakai `random_bytes()` untuk menghasilkan nilai acak bagi token login, kode reset, dan token formulir. | [`src/security.php` baris 26](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L26) |
| `token_hash()` | Memakai `hash()` dengan SHA-256. MySQL menyimpan hasil hash token login dan reset untuk pemeriksaan berikutnya. | [`src/security.php` baris 31](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L31) |
| `start_authentication()` | Membuat token login yang berlaku delapan jam, menyimpan hash-nya, lalu mengganti ID sesi PHP. | [`src/security.php` baris 220](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L220) |
| `load_user()` | Mencari akun dari token sesi dan memeriksa masa berlakunya sebelum PHP menjalankan rute. | [`src/security.php` baris 204](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L204) |
| `end_authentication()` | Menghapus token login aktif dari database. | [`src/security.php` baris 240](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L240) |
| `rotate_session()` | Mengosongkan data sesi dan memakai `session_regenerate_id()` untuk mengganti ID sesi PHP, misalnya setelah login atau logout. | [`src/security.php` baris 247](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L247) |
| `require_user()` | Mengarahkan pengguna yang belum masuk ke halaman login. Jika suatu tindakan membutuhkan peran tertentu, PHP memeriksa peran itu sebelum melanjutkan. | [`src/security.php` baris 255](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L255) |
| `csrf_input()` | Menaruh token dari sesi sebagai input tersembunyi pada formulir. | [`src/security.php` baris 168](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L168) |
| `verify_csrf()` | Memakai `hash_equals()` untuk membandingkan token yang dikirim formulir dengan token sesi. Ini membantu mencegah situs lain memakai sesi pengguna untuk mengirim formulir. | [`src/security.php` baris 174](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L174) |
| `throttle()` | Menghitung percobaan dalam batas waktu dan mengembalikan 429 jika batas terlampaui. Saat login, pemeriksaan dilakukan berdasarkan IP dan email. | [`src/security.php` baris 182](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L182), [`src/accounts.php` baris 41–45](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/accounts.php#L41-L45) |

### Isian formulir dan respons browser

| Fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `input()` | Mengambil nilai teks dari data formulir atau URL. Nilai yang hilang atau berbentuk array dibaca sebagai string kosong. | [`src/security.php` baris 36](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L36) |
| `text_length()` | Menghitung jumlah karakter Unicode untuk pengecekan panjang isian. | [`src/security.php` baris 42](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L42) |
| `field()` | Merapikan spasi luar lalu memeriksa apakah panjang dan isi teks sesuai batas yang diberikan. | [`src/security.php` baris 47](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L47) |
| `email_address()` | Mengubah email ke huruf kecil dan memeriksa formatnya sebelum digunakan. | [`src/security.php` baris 57](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L57) |
| `hash_password()` | Memeriksa panjang kata sandi dan memakai `password_hash()` untuk menghasilkan hash Argon2id sebelum disimpan. | [`src/security.php` baris 66](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L66) |
| `password_matches()` | Memakai `password_verify()` untuk memeriksa apakah kata sandi yang dimasukkan cocok dengan hash akun. | [`src/security.php` baris 75](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L75) |
| `validate_source_url()` | Memeriksa format tautan sumber. Fungsi menerima HTTP atau HTTPS tanpa nama pengguna atau kata sandi di URL. | [`src/security.php` baris 87](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L87) |
| `escape()` | Memakai `htmlspecialchars()` agar karakter khusus dari judul atau isian lain tampil sebagai teks di halaman. | [`src/security.php` baris 21](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L21) |
| `local_mode()` | Membaca apakah mode pengembangan lokal diaktifkan. | [`src/security.php` baris 105](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L105) |
| `secure_request()` | Membaca tanda HTTPS yang disediakan server. | [`src/security.php` baris 99](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L99) |
| `validate_transport()` | Memeriksa alamat host yang diizinkan. Mode lokal hanya menerima akses loopback, seperti `127.0.0.1`, dan mode deployment memerlukan HTTPS. | [`src/security.php` baris 125](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L125) |
| `security_headers()` | Mengatur cara browser memperlakukan halaman. Contohnya mencegah halaman disisipkan ke situs lain, membatasi sumber aset, dan meminta browser untuk tidak menyimpan halaman pribadi di cache. | [`src/security.php` baris 110](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L110) |
| `HttpError::__construct()` | Menyimpan kode HTTP dan pesan yang akan dicantumkan pada halaman kesalahan. | [`src/security.php` baris 10](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L10) |
| `reject()` | Menghentikan tindakan dengan `HttpError`, misalnya 403 untuk tindakan yang dilarang atau 422 untuk isian yang tidak valid. | [`src/security.php` baris 16](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/security.php#L16) |

## MySQL di src/database.php

| Fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `environment()` | Membaca pengaturan dari environment. Fungsi memakai nilai bawaan jika pengaturannya kosong atau belum diisi. | [`src/database.php` baris 6](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L6) |
| `data_directory()` | Menentukan lokasi penyimpanan file sesi. Bawaannya adalah folder `var/`. | [`src/database.php` baris 12](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L12) |
| `private_directory()` | Membuat direktori dengan izin privat ketika aplikasi memerlukan tempat menyimpan sesi. | [`src/database.php` baris 17](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L17) |
| `database()` | Membuka koneksi PHP ke MySQL dari pengaturan host, port, nama database, dan akun database. | [`src/database.php` baris 24](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L24) |
| `initialize_database()` | Membaca `schema.sql` dan menjalankan perintah untuk membuat tabel pada database yang telah dipilih. | [`src/database.php` baris 61](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L61) |
| `sql()` | Memakai PDO `prepare()` dan `execute()` untuk menjalankan query. Tanda `?` diisi dengan nilai secara terpisah, misalnya email yang dicari saat login. | [`src/database.php` baris 75](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L75) |
| `row()` | Mengambil satu baris hasil query. Fungsi mengembalikan `null` jika datanya kosong. | [`src/database.php` baris 82](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L82) |
| `transaction()` | Memulai transaksi dengan `beginTransaction()`, menyimpan semua langkah dengan `commit()`, atau membatalkannya dengan `rollBack()` jika ada yang gagal. | [`src/database.php` baris 88](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/database.php#L88) |

`schema.sql` menentukan tabel dan relasi. Satu akun klien dapat memiliki banyak kasus. Satu kasus dapat memiliki banyak bukti dan catatan riwayat, serta satu laporan. [`schema.sql` baris 34–87](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L34-L87)

| Tabel | Data yang disimpan | Kode |
| --- | --- | --- |
| `users` | Identitas, email, peran, dan hash kata sandi. | [`schema.sql` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L1) |
| `auth_sessions` | Hash token login, ID akun, dan waktu kedaluwarsa. | [`schema.sql` baris 11](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L11) |
| `password_resets` | Hash kode reset dan waktu kedaluwarsa untuk tiap akun. | [`schema.sql` baris 20](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L20) |
| `rate_limits` | Kunci penghitung, jumlah percobaan, dan waktu kedaluwarsa batas. | [`schema.sql` baris 27](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L27) |
| `cases` | Pengajuan, klien, analis, status, dan catatan keputusan. | [`schema.sql` baris 34](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L34) |
| `evidence` | Catatan sumber dan lampiran opsional per kasus. | [`schema.sql` baris 55](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L55) |
| `reports` | Ringkasan, temuan, dan batasan laporan per kasus. | [`schema.sql` baris 69](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L69) |
| `case_events` | Riwayat tindakan, waktu, dan akun pelaku per kasus. | [`schema.sql` baris 78](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/schema.sql#L78) |

## Tampilan di views dan src/render.php

| File atau fungsi | Bagian yang ditampilkan | Kode |
| --- | --- | --- |
| `views/home.html`, `views/services.html`, `views/sample_report.html` | Beranda, pilihan layanan, dan contoh laporan publik. | [`views/home.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/home.html#L1), [`views/services.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/services.html#L1), [`views/sample_report.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/sample_report.html#L1) |
| `views/auth.html` | Satu template dengan tiga mode untuk masuk, daftar, dan reset kata sandi. | [`views/auth.html` baris 1–6](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/auth.html#L1-L6) |
| `views/dashboard.html` dan `views/new_case.html` | Daftar kasus sesuai peran dan formulir pengajuan klien. | [`views/dashboard.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/dashboard.html#L1), [`views/new_case.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/new_case.html#L1) |
| `views/case.html` | Detail kasus, formulir keputusan admin, bukti, laporan, dan riwayat. | [`views/case.html` baris 8](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L8), [`views/case.html` baris 35](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L35), [`views/case.html` baris 75](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L75), [`views/case.html` baris 105](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L105), [`views/case.html` baris 125](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/case.html#L125) |
| `views/profile.html` dan `views/error.html` | Formulir profil dan perubahan kata sandi, serta halaman kesalahan. | [`views/profile.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/profile.html#L1), [`views/error.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/error.html#L1) |
| `views/head.html`, `views/public_head.html`, `views/public_links.html`, dan `views/workspace_head.html` | Bagian atas halaman, pemuatan CSS, navigasi publik, dan menu pengguna yang sudah masuk. | [`views/head.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/head.html#L1), [`views/public_head.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/public_head.html#L1), [`views/public_links.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/public_links.html#L1), [`views/workspace_head.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/workspace_head.html#L1) |
| `views/foot.html`, `views/public_foot.html`, dan `views/workspace_foot.html` | Penutup HTML serta bagian bawah halaman publik dan halaman kerja. | [`views/foot.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/foot.html#L1), [`views/public_foot.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/public_foot.html#L1), [`views/workspace_foot.html` baris 1](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/workspace_foot.html#L1) |
| `redirect()` | Mengirim respons 303 untuk mengarahkan browser ke halaman berikutnya setelah tindakan seperti menyimpan formulir. | [`src/render.php` baris 4](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/render.php#L4) |
| `flash()` | Menyimpan pesan sementara di sesi, misalnya pemberitahuan bahwa profil berhasil disimpan. | [`src/render.php` baris 10](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/render.php#L10) |
| `flash_messages()` | Mengambil pesan tersebut dan menghapusnya dari sesi agar tampil sekali. | [`src/render.php` baris 15](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/render.php#L15) |
| `render()` | Meneruskan data dari PHP ke template HTML yang sesuai. | [`src/render.php` baris 22](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/render.php#L22) |

Empat file CSS untuk tata letak, tampilan formulir, dan respons layar ada di `public/static/`. Tag pemuatnya ada di `views/head.html`. [`views/head.html` baris 7–10](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/views/head.html#L7-L10)

## Perintah terminal di bin/omnitrace.php

| Bagian | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `read_password()` | Membaca kata sandi dari prompt tersembunyi atau dari input yang disalurkan lewat terminal. | [`bin/omnitrace.php` baris 15](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/bin/omnitrace.php#L15) |
| `init-db` | Memanggil `initialize_database()` untuk membuat tabel MySQL. | [`bin/omnitrace.php` baris 62–64](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/bin/omnitrace.php#L62-L64) |
| `create-staff` | Membuat akun admin atau analis setelah memeriksa peran, nama, email, dan kata sandi. | [`bin/omnitrace.php` baris 68–83](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/bin/omnitrace.php#L68-L83) |
| `issue-reset` | Membuat kode reset yang berlaku 15 menit. Kode muncul di terminal dan hash-nya disimpan di MySQL. | [`bin/omnitrace.php` baris 84–102](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/bin/omnitrace.php#L84-L102) |
| `import-sqlite` | Menjalankan impor satu kali jika ada data dari versi lama. Perintah ini memakai file SQLite sebagai sumber dan database MySQL kosong sebagai tujuan. | [`bin/omnitrace.php` baris 65–67](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/bin/omnitrace.php#L65-L67) |

### Pemindahan data lama di src/legacy_import.php

| Fungsi | Penjelasan sederhana | Kode |
| --- | --- | --- |
| `read_legacy_table()` | Membaca kolom yang sudah dipilih dari tabel pada database lama. | [`src/legacy_import.php` baris 4](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/legacy_import.php#L4) |
| `import_legacy_database()` | Membuka file lama untuk dibaca, lalu menyalin akun, kasus, bukti beserta lampiran, laporan, dan riwayat ke MySQL dalam satu transaksi. | [`src/legacy_import.php` baris 14](https://github.com/RizkyPangestu/omnitrace/blob/47bf9a7659f175ea666c5fa330335a49f9e82c44/src/legacy_import.php#L14) |
