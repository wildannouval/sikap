<?php

use App\Models\KerjaPraktek;
use App\Models\Konsultasi;
use App\Models\Seminar;
use App\Models\SuratPengantar;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use function Livewire\Volt\{state, mount};

// Mendefinisikan semua properti state untuk semua peran
state([
    // Mahasiswa
    'activeKp' => null,
    'currentStatusText' => 'Belum ada pengajuan Kerja Praktik.',
    'nextActionText' => 'Ajukan Surat Pengantar',
    'nextActionRoute' => 'surat-pengantar.index',
    // Bapendik
    'suratBaruCount' => 0,
    'kpBaruCount' => 0,
    'seminarBaruCount' => 0,
    'spkTerbitCount' => 0,
    // Dosen Komisi
    'validasiKpCount' => 0,
    'penentuanPembimbingCount' => 0,
    // Dosen Pembimbing
    'mahasiswaBimbinganCount' => 0,
    'verifikasiBimbinganCount' => 0,
    'penilaianKpCount' => 0,
]);

// Logika yang berjalan saat halaman dimuat
mount(function () {
    $user = Auth::user();

    // Logika untuk MAHASISWA
    if ($user->role === 'Mahasiswa' && $user->mahasiswa) {
        $this->activeKp = KerjaPraktek::with('seminar')
            ->where('mahasiswa_id', $user->mahasiswa->id)
            ->latest()
            ->first();

        if ($this->activeKp) {
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

    // Logika untuk BAPENDIK
    if ($user->role === 'Bapendik') {
        $this->suratBaruCount = SuratPengantar::where('status_surat_pengantar', 'Diajukan')->count();
        $this->kpBaruCount = KerjaPraktek::where('status_pengajuan_kp', 'Diajukan')->count();
        $this->seminarBaruCount = Seminar::where('status_seminar', 'Diajukan')->count();
        $this->spkTerbitCount = KerjaPraktek::where('status_pengajuan_kp', 'Disetujui')->count();
    }

    // Logika untuk DOSEN KOMISI
    if ($user->role === 'Dosen Komisi' && $user->dosen) {
        $this->validasiKpCount = KerjaPraktek::where('status_pengajuan_kp', 'Proses di Komisi')->count();
        $this->penentuanPembimbingCount = KerjaPraktek::where('status_pengajuan_kp', 'Disetujui')
            ->whereNull('dosen_pembimbing_id')
            ->count();
    }

    // Logika untuk DOSEN PEMBIMBING
    if ($user->role === 'Dosen Pembimbing' && $user->dosen) {
        $dosenId = $user->dosen->id;
        $this->mahasiswaBimbinganCount = KerjaPraktek::where('dosen_pembimbing_id', $dosenId)->where('status_pengajuan_kp', 'SPK Terbit')->count();
        $this->verifikasiBimbinganCount = Konsultasi::where('dosen_pembimbing_id', $dosenId)->where('status_verifikasi', 'Menunggu Verifikasi')->count();
        $this->penilaianKpCount = Seminar::whereHas('kerjaPraktek', function ($query) use ($dosenId) {
            $query->where('dosen_pembimbing_id', $dosenId);
        })->where('status_seminar', 'Dijadwalkan')->count();
    }
});
?>

<div>
    @auth
        {{-- ================================================================= --}}
        {{-- TAMPILAN DASHBOARD UNTUK MAHASISWA --}}
        {{-- ================================================================= --}}
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

            {{-- ================================================================= --}}
            {{-- TAMPILAN DASHBOARD UNTUK BAPENDIK --}}
            {{-- ================================================================= --}}
        @elseif (auth()->user()->role === 'Bapendik')
            <div class="space-y-8">
                <flux:heading size="2xl" level="1">Dashboard Bapendik</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {{-- Kartu Statistik --}}
                    <a href="{{ route('bapendik.surat-pengantar') }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full">
                            <flux:icon name="envelope" class="size-8 text-blue-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $suratBaruCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pengajuan Surat Baru</p>
                        </flux:card>
                    </a>
                    <a href="{{ route('bapendik.pengajuan-kp') }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full">
                            <flux:icon name="document-plus" class="size-8 text-yellow-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $kpBaruCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Validasi Berkas KP</p>
                        </flux:card>
                    </a>
                    <a href="{{ route('bapendik.pengajuan-kp') }}?tab=penerbitan" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full">
                            <flux:icon name="document-check" class="size-8 text-green-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $spkTerbitCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Perlu Terbit SPK</p>
                        </flux:card>
                    </a>
                    <a href="{{ route('bapendik.penjadwalan-seminar') }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full">
                            <flux:icon name="calendar-days" class="size-8 text-purple-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $seminarBaruCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pendaftaran Seminar</p>
                        </flux:card>
                    </a>
                </div>
            </div>

            {{-- ================================================================= --}}
            {{-- TAMPILAN DASHBOARD UNTUK DOSEN --}}
            {{-- ================================================================= --}}
        @elseif (in_array(auth()->user()->role, ['Dosen Pembimbing', 'Dosen Komisi']))
            <div class="space-y-8">
                <flux:heading size="2xl" level="1">Dashboard Dosen</flux:heading>

                {{-- Khusus Dosen Komisi --}}
                @if(auth()->user()->role === 'Dosen Komisi')
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <a href="{{ route('doskom.validasi-kp') }}" class="block hover:scale-[1.02] transition-transform">
                            <flux:card class="h-full">
                                <flux:icon name="document-magnifying-glass" class="size-8 text-blue-500" />
                                <p class="mt-4 text-3xl font-bold">{{ $validasiKpCount }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Validasi Proposal KP</p>
                            </flux:card>
                        </a>
                        <a href="{{ route('doskom.validasi-kp') }}?tab=pembimbing" class="block hover:scale-[1.02] transition-transform">
                            <flux:card class="h-full">
                                <flux:icon name="user-plus" class="size-8 text-yellow-500" />
                                <p class="mt-4 text-3xl font-bold">{{ $penentuanPembimbingCount }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Tentukan Pembimbing</p>
                            </flux:card>
                        </a>
                    </div>
                @endif

                {{-- Khusus Dosen Pembimbing --}}
                @if(auth()->user()->role === 'Dosen Pembimbing')
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <a href="{{ route('dospem.mahasiswa') }}" class="block hover:scale-[1.02] transition-transform">
                            <flux:card class="h-full">
                                <flux:icon name="user-group" class="size-8 text-blue-500" />
                                <p class="mt-4 text-3xl font-bold">{{ $mahasiswaBimbinganCount }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Mahasiswa Bimbingan</p>
                            </flux:card>
                        </a>
                        <a href="{{ route('dospem.mahasiswa') }}" class="block hover:scale-[1.02] transition-transform">
                            <flux:card class="h-full">
                                <flux:icon name="chat-bubble-left-right" class="size-8 text-yellow-500" />
                                <p class="mt-4 text-3xl font-bold">{{ $verifikasiBimbinganCount }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Verifikasi Bimbingan</p>
                            </flux:card>
                        </a>
                        <a href="{{ route('dospem.penilaian') }}" class="block hover:scale-[1.02] transition-transform">
                            <flux:card class="h-full">
                                <flux:icon name="clipboard-document-check" class="size-8 text-green-500" />
                                <p class="mt-4 text-3xl font-bold">{{ $penilaianKpCount }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Perlu Dinilai</p>
                            </flux:card>
                        </a>
                    </div>
                @endif
            </div>
        @endif
    @endauth
</div>
