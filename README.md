# OmniTrace

Aplikasi web untuk mengelola pengajuan riset berbasis sumber publik. Prototipe HTML/CSS OMNITRACE BIFEST menjadi dasar antarmuka. Aplikasi menyimpan kasus, catatan bukti, keputusan, dan laporan di SQLite.

## Jalankan untuk demo lokal

Gunakan Python 3.11 atau yang lebih baru. Jalankan perintah dari folder proyek.

```bash
python -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt
export OMNITRACE_LOCAL=1
export OMNITRACE_SECRET="$(python -c 'import secrets; print(secrets.token_urlsafe(48))')"
python -m flask --app app init-db
python -m flask --app app create-staff admin admin@example.org "Demo Admin"
python -m flask --app app create-staff analyst analyst@example.org "Demo Analyst"
python serve.py
```

Pada Windows PowerShell, gunakan `.venv\Scripts\Activate.ps1` dan set variabel dengan sintaks berikut. Perintah instalasi, pembuatan akun, dan menjalankan server sama dengan contoh di atas.

```powershell
$env:OMNITRACE_LOCAL = "1"
$env:OMNITRACE_SECRET = (python -c "import secrets; print(secrets.token_urlsafe(48))")
```

Buka [OmniTrace](http://127.0.0.1:5000). CLI meminta kata sandi akun staf secara interaktif. Daftarkan akun klien lewat **Create an account**. Gunakan profil browser terpisah atau keluar dari akun sebelum berpindah peran. Folder `instance/` menyimpan database lokal. Mengganti secret akan mengakhiri sesi browser yang ada.

## Alur demo

1. Klien masuk, pilih **Request investigation**, isi tujuan dan konfirmasi kewenangan, lalu kirim brief.
2. Admin membuka request dan memilih **Approve and assign** untuk menugaskan analis. Admin juga dapat menolak dengan alasan.
3. Analis membuka kasus yang ditugaskan, menyimpan catatan sumber dan lampiran jika diperlukan, lalu pilih **Submit for review** pada laporan.
4. Admin meninjau isi laporan dan memilih **Publish report** atau **Request revision**. Analis dapat memperbaiki dan mengirim ulang laporan.
5. Klien pemilik membuka dan mengunduh laporan terbit dari kasusnya. Klien kedua melihat daftar kasusnya sendiri.

Untuk pemulihan kata sandi, operator yang sudah memverifikasi pemilik akun menjalankan perintah berikut. Bagikan kode yang dihasilkan melalui kanal privat. Pengguna memasukkan kode pada **Forgot password?** dalam 15 menit.

```bash
python -m flask --app app issue-reset client@example.org
```

## Konfigurasi HTTPS

Mode lokal menggunakan HTTP pada loopback. Untuk deployment, hapus `OMNITRACE_LOCAL`, pasang secret acak yang persisten melalui secret manager atau konfigurasi layanan, lalu atur `OMNITRACE_HOSTS` ke nama domain yang digunakan. Arahkan trafik HTTPS dari satu reverse proxy pada mesin yang sama ke Waitress di `127.0.0.1:5000`. Proxy harus menetapkan `Host` yang sah, menimpa `X-Forwarded-For` dengan IP klien yang diketahui, menimpa `X-Forwarded-Proto` sesuai koneksi HTTPS, dan mengalihkan HTTP ke HTTPS. Konfigurasi ini sesuai dengan kepercayaan satu proxy loopback di `serve.py`.

Opsional, gunakan `OMNITRACE_DB` untuk memilih berkas SQLite pada direktori privat dan `OMNITRACE_PORT` untuk mengubah port internal. Jalankan aplikasi sebagai akun layanan dengan akses terbatas. Kelola izin, cadangan, dan enkripsi penyimpanan pada lingkungan deployment.

Aplikasi demo tidak menagih pembayaran atau mengambil URL sumber dari jaringan. Pemeriksaan lampiran membatasi ukuran dan mengenali awalan berkas, lalu menyimpannya privat dan menyajikannya sebagai unduhan. Pemindaian malware dan verifikasi kepemilikan email membutuhkan layanan tambahan. Gunakan data fiktif saat demonstrasi dan saat bertukar kode.

## Laporan tugas

- [Dokumentasi OmniTrace](Laporan/01-Dokumentasi-OmniTrace.docx)
- [Draf code review kelompok pasangan](Laporan/02-Draf-Code-Review-Kelompok-Pasangan.docx). Temuan aktual dan bukti per temuan membutuhkan source code kelompok pasangan.
