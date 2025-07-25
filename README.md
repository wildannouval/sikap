# SIKAP (Sistem Informasi Pengelolaan Kerja Praktik)

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel)
![Livewire](https://img.shields.io/badge/Livewire-3-4F46E5?style=for-the-badge&logo=livewire)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php)

SIKAP adalah aplikasi web yang dirancang untuk mengelola seluruh alur kerja **Kerja Praktik (KP)** secara digital, mulai dari pengajuan surat pengantar hingga penilaian akhir. Aplikasi ini dibangun untuk memfasilitasi interaksi antara Mahasiswa, Bapendik, Dosen Komisi, dan Dosen Pembimbing secara efisien dan transparan.

---

## Fitur Utama

Aplikasi ini mencakup alur kerja multi-peran yang lengkap:

#### 👤 Untuk Mahasiswa
- **Dashboard Progresif**: Tampilan *stepper* visual untuk memantau tahapan KP.
- **Pengajuan Surat Pengantar**: Form untuk meminta surat pengantar resmi.
- **Pengajuan KP**: Form untuk mengajukan proposal dan surat penerimaan dari perusahaan.
- **Logbook Bimbingan**: Mencatat dan mengirimkan catatan bimbingan untuk diverifikasi.
- **Pendaftaran Seminar**: Form pendaftaran seminar dengan syarat minimal bimbingan.
- **Lihat Nilai**: Halaman untuk melihat hasil akhir KP dan mengunggah bukti distribusi laporan.

#### 👨‍💼 Untuk Staf (Bapendik & Dosen Komisi)
- **Dashboard Informatif**: Menampilkan ringkasan tugas yang perlu diproses.
- **Validasi Multi-Tahap**: Alur validasi untuk Surat Pengantar, Pengajuan KP, dan Penjadwalan Seminar.
- **Penentuan Dosen Pembimbing**: Fitur bagi Dosen Komisi untuk menugaskan pembimbing.
- **Penjadwalan Cerdas**: "Asisten Jadwal" untuk menghindari bentrok dan alur konfirmasi jadwal dengan mahasiswa.
- **Manajemen Data Master**: CRUD untuk data Pengguna (Mahasiswa & Dosen), Jurusan, dan Ruangan.
- **Laporan & Arsip**: Halaman terpusat dengan fitur *search*, *filter*, *sort*, dan **ekspor data ke Excel**.

#### ✨ Fitur Sistem
- **Sistem Notifikasi**: Notifikasi *real-time* di dalam aplikasi untuk setiap pembaruan status.
- **Kalender Seminar**: Tampilan kalender interaktif untuk melihat semua jadwal seminar.
- **Manajemen Peran**: Hak akses yang terpisah untuk setiap peran pengguna.

---

## Teknologi yang Digunakan

* **Backend**: Laravel 12, PHP 8.3
* **Frontend**: Livewire 3, Laravel Volt, Alpine.js
* **UI Framework**: Flux UI Pro
* **Database**: MySQL
* **Lingkungan Development Lokal**: Laravel Herd
* **Deployment**: Server VPS (LEMP Stack) dengan CI/CD menggunakan GitHub Actions

---

## Instalasi (Untuk Development Lokal)

Berikut adalah cara untuk menjalankan proyek ini di lingkungan lokal:

1.  **Clone Repository**
    ```bash
    git clone [https://github.com/username/sikap.git](https://github.com/username/sikap.git)
    cd sikap
    ```

2.  **Install Dependensi**
    ```bash
    composer install
    npm install
    ```

3.  **Konfigurasi Environment**
    * Salin file `.env.example` menjadi `.env`.
        ```bash
        cp .env.example .env
        ```
    * Buat kunci aplikasi baru.
        ```bash
        php artisan key:generate
        ```
    * Buka file `.env` dan sesuaikan konfigurasi database Anda (DB_DATABASE, DB_USERNAME, DB_PASSWORD).

4.  **Setup Database & Aset**
    * Jalankan migrasi dan *seeder* untuk mengisi database dengan data awal yang realistis.
        ```bash
        php artisan migrate:fresh --seed
        ```
    * Buat *symbolic link* untuk *storage*.
        ```bash
        php artisan storage:link
        ```

5.  **Jalankan Aplikasi**
    * Jalankan Vite untuk *compiling* aset frontend.
        ```bash
        npm run dev
        ```
    * Di terminal lain, jalankan server development Laravel (atau gunakan Laravel Herd).
        ```bash
        php artisan serve
        ```

Aplikasi sekarang bisa diakses di `http://localhost:8000`.

---

## Akun untuk Testing

Gunakan akun berikut untuk login dan menguji setiap peran. **Password** untuk semua akun adalah **`password`**.

| Peran              | Email                   |
| ------------------ | ----------------------- |
| Bapendik           | `bapendik@sikap.test`   |
| Dosen Komisi       | `doskom@sikap.test`     |
| Dosen Pembimbing   | `dosen1@sikap.test`     |
| Mahasiswa          | `mahasiswa1@sikap.test` |

*Terdapat juga data acak untuk `dosen2` - `dosen10` dan `mahasiswa2` - `mahasiswa30`.*

---

## Deployment

Aplikasi ini di-deploy ke server VPS menggunakan alur kerja CI/CD dengan **GitHub Actions**. Skrip deployment otomatis berjalan setiap kali ada *push* ke *branch* `main`.
