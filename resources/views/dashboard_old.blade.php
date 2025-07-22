<?php

use App\Models\KerjaPraktek;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use function Livewire\Volt\{state, mount};

// Mendefinisikan semua properti dengan nilai awal menggunakan state()
state([
    'activeKp' => null,
    'currentStatusText' => 'Belum ada pengajuan Kerja Praktik.',
    'nextActionText' => 'Ajukan Surat Pengantar',
    'nextActionRoute' => 'surat-pengantar.index',
]);

// Logika yang tadinya di dalam class, sekarang ada di dalam fungsi mount()
mount(function () {
    $user = Auth::user();

    if ($user->role === 'Mahasiswa' && $user->mahasiswa) {
        $this->activeKp = KerjaPraktek::with('seminar')
            ->where('mahasiswa_id', $user->mahasiswa->id)
            ->latest()
            ->first();

        if ($this->activeKp) {
            // Cek status seminar dulu, jika tidak ada baru cek status KP
            $status = optional($this->activeKp->seminar)->status_seminar ?? $this->activeKp->status_pengajuan_kp;

            switch ($status) {
                case 'Diajukan':
                case 'Proses di Komisi':
                    $this->currentStatusText = 'Pengajuan KP Anda sedang direview.';
                    $this->nextActionText = 'Lihat Status Pengajuan';
                    $this->nextActionRoute = 'kp.pengajuan';
                    break;
                case 'Disetujui':
                    $this->currentStatusText = 'Pengajuan KP Anda telah disetujui. Menunggu penerbitan SPK oleh Bapendik.';
                    $this->nextActionText = 'Lihat Status Pengajuan';
                    $this->nextActionRoute = 'kp.pengajuan';
                    break;
                case 'SPK Terbit':
                    $this->currentStatusText = 'SPK Anda telah terbit! Anda sudah bisa memulai bimbingan.';
                    $this->nextActionText = 'Mulai Bimbingan (Logbook)';
                    $this->nextActionRoute = 'kp.bimbingan';
                    break;
                case 'Dijadwalkan':
                    $this->currentStatusText = 'Seminar Anda telah dijadwalkan.';
                    $this->nextActionText = 'Lihat Detail Pendaftaran';
                    $this->nextActionRoute = 'seminar.pendaftaran';
                    break;
                case 'Dinilai':
                    $this->currentStatusText = 'Selamat! Kerja Praktik Anda telah selesai dinilai.';
                    $this->nextActionText = 'Lihat Nilai Akhir';
                    $this->nextActionRoute = 'kp.nilai';
                    break;
                case 'Ditolak':
                    $this->currentStatusText = 'Mohon maaf, pengajuan KP Anda ditolak.';
                    $this->nextActionText = 'Lihat Detail Pengajuan';
                    $this->nextActionRoute = 'kp.pengajuan';
                    break;
            }
        }
    }
});

?>

{{-- Bagian Blade/HTML tidak perlu diubah, hanya pastikan menggunakan sintaks yang benar --}}
<x-layouts.app :title="__('Dashboard')">
    @auth
        @if (auth()->user()->role === 'Mahasiswa')
            <div class="space-y-8">
                <div>
                    <flux:heading size="2xl" level="1">Selamat Datang, {{ auth()->user()->name }}!</flux:heading>
                    <flux:subheading size="lg">Ini adalah ringkasan dari proses Kerja Praktik Anda.</flux:subheading>
                </div>
                <flux:card>
                    <div class="flex flex-col items-center justify-center text-center">
                        <flux:label>Status Terkini</flux:label>
                        <p class="mt-1 text-lg font-semibold">{{ $currentStatusText }}</p>
                        @if($nextActionRoute)
                            <flux:button as="a" href="{{ route($nextActionRoute) }}" variant="primary" class="mt-4">
                                {{ $nextActionText }}
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            </div>
        @elseif (in_array(auth()->user()->role, ['Bapendik']))
            {{-- Placeholder Bapendik --}}
            <flux:heading size="2xl" level="1">Dashboard Bapendik</flux:heading>
        @elseif (in_array(auth()->user()->role, ['Dosen Pembimbing', 'Dosen Komisi']))
            {{-- Placeholder Dosen --}}
            <flux:heading size="2xl" level="1">Dashboard Dosen</flux:heading>
        @endif
    @endauth
</x-layouts.app>
