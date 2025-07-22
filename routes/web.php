<?php

use App\Http\Controllers\KerjaPraktekController;
use App\Http\Controllers\SeminarController;
use App\Http\Controllers\SuratPengantarController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

//Route::view('dashboard', 'dashboard')
//    ->middleware(['auth', 'verified'])
//    ->name('dashboard');

Route::middleware(['auth'])->group(function () {

    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Grup Route untuk Mahasiswa
    Route::middleware('role:Mahasiswa')->prefix('mahasiswa')->group(function () {
        Volt::route('surat-pengantar', 'mahasiswa.surat-pengantar.index')
            ->name('surat-pengantar.index');

        // Nanti kita tambahkan route lain di sini
        Volt::route('/kp-pengajuan', 'mahasiswa.kp.pengajuan')->name('kp.pengajuan');
        Volt::route('/bimbingan', 'mahasiswa.kp.bimbingan')->name('kp.bimbingan');
        Volt::route('/seminar', 'mahasiswa.seminar.pendaftaran')->name('seminar.pendaftaran');
        Volt::route('/nilai', 'mahasiswa.kp.nilai')->name('kp.nilai');
    });

    // Grup Route untuk Bapendik
    Route::middleware('role:Bapendik')->prefix('bapendik')->group(function () {
        Volt::route('surat-pengantar', 'bapendik.surat-pengantar.index')
            ->name('bapendik.surat-pengantar');

        // Route sementara untuk link lainnya agar tidak error
        Volt::route('/validasi-kp', 'bapendik.kp.validasi')->name('bapendik.pengajuan-kp');
        Volt::route('/penjadwalan-seminar', 'bapendik.seminar.penjadwalan')->name('bapendik.penjadwalan-seminar');
        Route::get('/laporan', function() { return 'Halaman Laporan'; })->name('bapendik.laporan');

    });

    // Grup Route untuk Data Master (Bapendik)
    Route::middleware('role:Bapendik')->prefix('master')->group(function () {
        // di dalam grup Route::middleware('role:Bapendik')->prefix('master')
        Volt::route('/pengguna', 'bapendik.master.pengguna.index')->name('master.pengguna');
        Volt::route('/ruangan', 'bapendik.master.ruangan.index')->name('master.ruangan');
        Volt::route('/jurusan', 'bapendik.master.jurusan.index')->name('master.jurusan');
    });

    // Grup Route untuk Dosen Komisi
    Route::middleware('role:Dosen Komisi')->prefix('dosen-komisi')->group(function () {
        Volt::route('/validasi-kp', 'dosen-komisi.kp.validasi')
            ->name('doskom.validasi-kp');

        // Route sementara untuk link lainnya agar tidak error
        Route::get('/mahasiswa-bimbingan', function() { return 'Halaman Mahasiswa Bimbingan (Komisi)'; })->name('doskom.mahasiswa');
        Route::get('/penilaian-kp', function() { return 'Halaman Penilaian KP (Komisi)'; })->name('doskom.penilaian');
        Route::get('/laporan', function() { return 'Halaman Laporan & Arsip (Komisi)'; })->name('doskom.laporan');
    });

    // Grup Route untuk Dosen Pembimbing
    Route::middleware('role:Dosen Pembimbing')->prefix('dosen-pembimbing')->group(function () {
        Volt::route('/mahasiswa-bimbingan', 'dosen-pembimbing.bimbingan.index')
            ->name('dospem.mahasiswa');

        Volt::route('/bimbingan/{kp}', 'dosen-pembimbing.bimbingan.detail')
            ->name('dospem.bimbingan.detail');

        // Route sementara untuk link lainnya agar tidak error
        Route::get('/jadwal-seminar', function() { return 'Halaman Jadwal Seminar (Dospem)'; })->name('dospem.jadwal-seminar');
        Volt::route('/penilaian', 'dosen-pembimbing.penilaian.index')->name('dospem.penilaian');
    });

    Route::get('/surat-pengantar/{id}/export', [SuratPengantarController::class, 'exportWord'])
        ->name('surat-pengantar.export');

    Route::get('/kerja-praktek/{id}/export-spk', [KerjaPraktekController::class, 'exportSpk'])
        ->name('kp.export-spk');

    Route::get('/seminar/{id}/export-berita-acara', [SeminarController::class, 'exportBeritaAcara'])
        ->name('seminar.export-berita-acara');
});

require __DIR__.'/auth.php';
