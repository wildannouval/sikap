<?php

use App\Http\Controllers\KerjaPraktekController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\SeminarController;
use App\Http\Controllers\SuratPengantarController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // --- RUTE PUBLIK (Bisa diakses semua peran yang login) ---
    Volt::route('/notifications', 'notifications.index')->name('notifications.index');
    Volt::route('/kalender-seminar', 'seminar.kalender')->name('seminar.kalender');
    Route::get('/surat-pengantar/{id}/export', [SuratPengantarController::class, 'exportWord'])->name('surat-pengantar.export');
    Route::get('/kerja-praktek/{id}/export-spk', [KerjaPraktekController::class, 'exportSpk'])->name('kp.export-spk');
    Route::get('/seminar/{id}/export-berita-acara', [SeminarController::class, 'exportBeritaAcara'])->name('seminar.export-berita-acara');
    Route::get('/master/pengguna/template', [PenggunaController::class, 'downloadTemplate'])
        ->name('master.pengguna.template');

    // --- RUTE MAHASISWA ---
    Route::middleware('role:Mahasiswa')->prefix('mahasiswa')->group(function () {
        Volt::route('surat-pengantar', 'mahasiswa.surat-pengantar.index')->name('surat-pengantar.index');
        Volt::route('/kp-pengajuan', 'mahasiswa.kp.pengajuan')->name('kp.pengajuan');
        Volt::route('/bimbingan', 'mahasiswa.kp.bimbingan')->name('kp.bimbingan');
        Volt::route('/seminar', 'mahasiswa.seminar.pendaftaran')->name('seminar.pendaftaran');
        Volt::route('/nilai', 'mahasiswa.kp.nilai')->name('kp.nilai');
    });

    // --- RUTE BAPENDIK ---
    Route::middleware('role:Bapendik')->prefix('bapendik')->group(function () {
        Volt::route('surat-pengantar', 'bapendik.surat-pengantar.index')->name('bapendik.surat-pengantar');
        Volt::route('/validasi-kp', 'bapendik.kp.validasi')->name('bapendik.pengajuan-kp');
        Volt::route('/penjadwalan-seminar', 'bapendik.seminar.penjadwalan')->name('bapendik.penjadwalan-seminar');
    });
    Route::middleware('role:Bapendik')->prefix('master')->group(function () {
        Volt::route('/pengguna', 'bapendik.master.pengguna.index')->name('master.pengguna');
        Volt::route('/ruangan', 'bapendik.master.ruangan.index')->name('master.ruangan');
        Volt::route('/jurusan', 'bapendik.master.jurusan.index')->name('master.jurusan');
    });

    // Grup Route untuk Data Master (Bapendik)
    Route::middleware('role:Bapendik')->prefix('master')->group(function () {
        // di dalam grup Route::middleware('role:Bapendik')->prefix('master')
        Volt::route('/pengguna', 'bapendik.master.pengguna.index')->name('master.pengguna');
        Volt::route('/ruangan', 'bapendik.master.ruangan.index')->name('master.ruangan');
        Volt::route('/jurusan', 'bapendik.master.jurusan.index')->name('master.jurusan');
    });

    // --- RUTE DOSEN KOMISI ---
    Route::middleware('role:Dosen Komisi')->prefix('dosen-komisi')->group(function () {
        Volt::route('/validasi-kp', 'dosen-komisi.kp.validasi')->name('doskom.validasi-kp');
    });

    // --- RUTE DOSEN PEMBIMBING (DAN DOSEN KOMISI) ---
    Route::middleware('role:Dosen Pembimbing,Dosen Komisi')->prefix('dosen-pembimbing')->group(function () {
        Volt::route('/mahasiswa-bimbingan', 'dosen-pembimbing.bimbingan.index')->name('dospem.mahasiswa');
        Volt::route('/bimbingan/{kp}', 'dosen-pembimbing.bimbingan.detail')->name('dospem.bimbingan.detail');
        Volt::route('/penilaian', 'dosen-pembimbing.penilaian.index')->name('dospem.penilaian');
        // Route placeholder ini bisa dihapus jika tidak ada halaman spesifik
        // Route::get('/jadwal-seminar', function() { return 'Halaman Jadwal Seminar (Dospem)'; })->name('dospem.jadwal-seminar');
    });

    // --- RUTE BERSAMA (BAPENDIK DAN DOSEN KOMISI) ---
    Route::middleware('role:Bapendik,Dosen Komisi,Dosen Pembimbing')->group(function () {
        Volt::route('/laporan-arsip', 'bapendik.laporan.index')->name('bapendik.laporan');
        Route::get('/laporan/export-kp', [LaporanController::class, 'exportKp'])->name('laporan.export-kp');
    });
});

require __DIR__.'/auth.php';
