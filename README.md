# Auto Pantau

Dashboard monitoring berbasis PHP untuk memantau database MySQL/PostgreSQL dan jumlah request aktif pada server aplikasi. Sistem dapat membatalkan query panjang, membersihkan koneksi MySQL yang tidur terlalu lama, melakukan restart server aplikasi ketika threshold terlampaui, serta mengirim notifikasi Telegram.

## Fitur

- Dashboard monitoring modern dan responsif.
- Mendukung beberapa server MySQL dari satu handler.
- Mendukung beberapa server PostgreSQL dari satu handler.
- Monitoring jumlah request aktif server aplikasi.
- Auto-kill koneksi/query MySQL berdasarkan timeout.
- Auto-cancel query PostgreSQL berdasarkan timeout.
- Auto-restart server aplikasi setelah threshold terlampaui beberapa kali berturut-turut.
- Notifikasi Telegram yang dapat diaktifkan atau dimatikan.
- Jadwal operasional berdasarkan tanggal 1–31.
- Pengaturan database aktif/nonaktif melalui UI.
- Interval polling dan auto-refresh yang dapat diubah.
- Kompatibel dengan PHP 5.5.

## Struktur File

- `index.php` — dashboard utama.
- `settings.php` — UI pengaturan sistem.
- `settings-lib.php` — fungsi baca/tulis pengaturan dan evaluasi jadwal.
- `settings.json` — pengaturan runtime; tidak masuk Git.
- `config.php` — koneksi database, Telegram, dan konfigurasi restart; tidak masuk Git.
- `config.example.php` — contoh konfigurasi yang aman disimpan di Git.
- `fetch-mysql.php` — monitoring dan auto-kill MySQL.
- `fetch-postgresql.php` — monitoring dan auto-cancel PostgreSQL.
- `fetch-app.php` — monitoring request aktif dan auto-restart aplikasi.
- `telegram.php` — pengiriman notifikasi Telegram.
- `.gitignore` — mengecualikan konfigurasi rahasia dan pengaturan runtime.

## Persyaratan

- PHP 5.5 atau lebih baru.
- Ekstensi PHP:
  - `curl`
  - `mysqli`
  - `pgsql`
  - `json`
  - `session`
- Web server seperti Apache atau Nginx.
- Akses jaringan dari web server ke database, Telegram API, endpoint status aplikasi, dan endpoint restart.

Untuk PostgreSQL yang menggunakan autentikasi SCRAM-SHA-256, client `libpq` harus versi 10 atau lebih baru. Jika server masih memakai PHP/libpq lama, gunakan metode autentikasi yang kompatibel atau perbarui runtime.

## Instalasi

1. Salin contoh konfigurasi:

```bash
cp config.example.php config.php
```

2. Isi `config.php` dengan koneksi dan key sebenarnya.

3. Pastikan `settings.json` dapat ditulis oleh user web server. Contoh pada sistem yang menggunakan `www-data`:

```bash
sudo chown www-data:www-data settings.json
sudo chmod 660 settings.json
```

4. Pastikan session PHP aktif dan direktori session dapat ditulis.

5. Buka `index.php` melalui web server.

6. Gunakan menu **Pengaturan** untuk menentukan jadwal dan fitur yang aktif.

## Konfigurasi Rahasia

Semua konfigurasi sensitif disimpan dalam `config.php`, termasuk:

- Host, port, database, username, dan password MySQL.
- Host, port, database, username, dan password PostgreSQL.
- Bot token dan chat ID Telegram.
- URL API restart.
- Instance ID aplikasi.
- URL endpoint status aplikasi.

`config.php` sudah tercantum dalam `.gitignore`. Jangan commit file tersebut.

Jika `config.php` pernah masuk Git, hapus dari tracking:

```bash
git rm --cached config.php
```

Jika kredensial pernah terpublikasi atau masuk riwayat Git, lakukan rotasi password dan token.

## Menambah Server Database

### MySQL

Tambahkan item baru di bagian `mysql` pada `config.php`:

```php
'g' => array(
    'label' => 'G',
    'enabled' => true,
    'host' => 'MYSQL_HOST',
    'port' => 3306,
    'database' => 'MYSQL_DATABASE',
    'username' => 'MYSQL_USERNAME',
    'password' => 'MYSQL_PASSWORD',
    'charset' => 'latin1'
)
```

Setelah itu, server akan tersedia pada menu pengaturan. Tidak perlu membuat file fetch baru.

### PostgreSQL

Tambahkan item baru di bagian `postgresql`:

```php
'analytics' => array(
    'label' => 'ANALYTICS',
    'enabled' => true,
    'host' => 'POSTGRESQL_HOST',
    'port' => 5432,
    'database' => 'POSTGRESQL_DATABASE',
    'username' => 'POSTGRESQL_USERNAME',
    'password' => 'POSTGRESQL_PASSWORD',
    'connect_timeout' => 10
)
```

## Monitoring MySQL

`fetch-mysql.php` memantau `INFORMATION_SCHEMA.PROCESSLIST`.

Nilai default pada kode:

- Query timeout: 60 detik.
- Sleep timeout normal: 300 detik.
- Sleep timeout saat tekanan tinggi: 60 detik.
- Ambang penggunaan koneksi: 80%.
- Ambang jumlah koneksi sleep: 50.

Tindakan:

- Koneksi `Sleep` dibunuh dengan `mysql.rds_kill`.
- Query panjang dibatalkan dengan `mysql.rds_kill_query`.
- Proses monitor sendiri dikecualikan.
- Tindakan hanya dilakukan pada jadwal aktif.

## Monitoring PostgreSQL

`fetch-postgresql.php` membaca query aktif dari `pg_stat_activity`.

Nilai default:

- Query timeout: 60 detik.
- Semua query aktif ditampilkan.
- Query yang melewati timeout dibatalkan dengan `pg_cancel_backend(pid)`.

`pg_cancel_backend()` membatalkan query, tetapi tidak memutuskan koneksi client. User PostgreSQL harus memiliki hak yang diperlukan, misalnya `pg_signal_backend` pada versi yang mendukungnya.

## Monitoring dan Restart Aplikasi

`fetch-app.php` membaca halaman status Apache dan mencari pola:

```text
123 requests currently being processed
```

Konfigurasi aplikasi berada di bagian `app_monitor` pada `config.php`.

Nilai saat ini:

- APP UTAMA: restart jika request aktif lebih dari 530.
- APP 01: restart jika request aktif lebih dari 300.
- Kondisi harus terjadi tiga kali berturut-turut.

Restart hanya dijalankan jika:

- Jadwal sedang aktif.
- Opsi **Auto restart server APP** aktif.
- Counter telah mencapai jumlah percobaan yang dikonfigurasi.

Jika request kembali normal, counter di-reset menjadi nol. Kegagalan mengambil endpoint status juga menambah counter, mengikuti perilaku sistem awal.

Counter disimpan dalam PHP session. Karena itu, counter terkait dengan sesi browser yang menjalankan dashboard dan akan berhenti bertambah apabila dashboard tidak lagi melakukan polling.

## Pengaturan Runtime

Pengaturan disimpan dalam `settings.json` dan dapat diubah melalui `settings.php`.

Pengaturan yang tersedia:

- Notifikasi Telegram aktif/nonaktif.
- Auto-restart server APP aktif/nonaktif.
- Auto-refresh halaman dashboard aktif/nonaktif.
- Interval auto-refresh halaman.
- Interval polling monitoring.
- Aktivitas setiap koneksi database.
- Zona waktu.
- Pembatasan jadwal aktif/nonaktif.
- Jadwal tanggal 1–31 beserta jam mulai dan selesai.

Tanggal yang tidak tersedia dalam suatu bulan otomatis tidak digunakan. Contohnya, tanggal 31 tetap tersedia pada UI tetapi tidak pernah digunakan pada bulan September.

Jika pembatasan jadwal dimatikan, sistem dianggap selalu aktif.

## Telegram

`telegram.php` membaca konfigurasi dari bagian `telegram` di `config.php`.

Notifikasi dapat dikendalikan dari menu pengaturan. Jika dimatikan, fungsi Telegram tidak membuka koneksi ke API.

Notifikasi digunakan untuk:

- Hasil kill query/koneksi MySQL.
- Hasil cancel query PostgreSQL.
- Hasil percobaan restart server aplikasi.

## Cara Kerja Dashboard

- Database yang aktif dimuat secara dinamis berdasarkan `config.php` dan `settings.json`.
- Monitoring dipanggil melalui AJAX sesuai interval polling.
- Monitoring database hanya dimuat pada jadwal aktif.
- App monitor tetap ditampilkan di luar jadwal, tetapi tidak melakukan restart.
- Auto-refresh halaman dapat diatur secara terpisah dari auto-restart server aplikasi.

Dashboard harus tetap terbuka agar polling AJAX terus berjalan. Jika membutuhkan monitoring tanpa browser, panggil endpoint melalui cron, supervisor, atau worker terpisah dengan mempertimbangkan session/counter app monitor.

## Keamanan

- Jangan menaruh token atau password di file selain `config.php`.
- Jangan membuka `settings.php` ke internet tanpa autentikasi tambahan.
- Batasi akses dashboard berdasarkan IP, VPN, atau autentikasi web server.
- Gunakan user database dengan hak minimum yang dibutuhkan.
- Lindungi endpoint restart dengan autentikasi dan pembatasan jaringan.
- Gunakan HTTPS.
- Lakukan rotasi token/password jika pernah terekspos.

## Validasi Sintaks

Untuk memeriksa semua file PHP:

```bash
for file in *.php; do php -l "$file" || exit 1; done
```

Untuk memeriksa `settings.json`:

```bash
php -r '$data = json_decode(file_get_contents("settings.json"), true); exit(is_array($data) ? 0 : 1);'
```
