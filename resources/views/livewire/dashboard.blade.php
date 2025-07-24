<?php

use App\Models\KerjaPraktek;
use App\Models\Konsultasi;
use App\Models\Seminar;
use App\Models\SuratPengantar;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use function Livewire\Volt\{state, mount};
use Carbon\Carbon;

// --- STATE MANAGEMENT ---
// Mendefinisikan semua properti state untuk semua peran dengan nilai awalnya
state([
    // Mahasiswa
    'activeKp' => null,
    'steps' => [], // Stepper akan dibangun secara dinamis
    // Bapendik
    'suratBaruCount' => 0,
    'kpBaruCount' => 0,
    'seminarBaruCount' => 0,
    'spkTerbitCount' => 0,
    'recentPengajuanKp' => collect(),
    // Dosen Komisi
    'validasiKpCount' => 0,
    'recentValidasiKp' => collect(),
    // Dosen Pembimbing
    'mahasiswaBimbinganCount' => 0,
    'verifikasiBimbinganCount' => 0,
    'penilaianKpCount' => 0,
    'recentBimbingan' => collect(),
]);

// --- LOGIC ---
// Logika yang berjalan saat halaman dimuat
mount(function () {
    $user = Auth::user();

    // Logika untuk MAHASISWA
    if ($user->role === 'Mahasiswa' && $user->mahasiswa) {
        $this->activeKp = KerjaPraktek::with(['seminar', 'konsultasis'])->where('mahasiswa_id', $user->mahasiswa->id)->latest()->first();

        $baseSteps = [
            1 => ['name' => 'Surat Pengantar', 'status' => 'upcoming', 'subtext' => 'Membuat surat pengantar untuk perusahaan.', 'href' => 'surat-pengantar.index'],
            2 => ['name' => 'Pengajuan KP', 'status' => 'upcoming', 'subtext' => 'Mengajukan proposal dan surat penerimaan.', 'href' => 'kp.pengajuan'],
            3 => ['name' => 'Bimbingan', 'status' => 'upcoming', 'subtext' => 'Melakukan bimbingan dengan dosen pembimbing.', 'href' => 'kp.bimbingan'],
            4 => ['name' => 'Seminar', 'status' => 'upcoming', 'subtext' => 'Mendaftar dan melaksanakan seminar.', 'href' => 'seminar.pendaftaran'],
            5 => ['name' => 'Selesai', 'status' => 'upcoming', 'subtext' => 'Melihat nilai akhir dan menyelesaikan administrasi.', 'href' => 'kp.nilai'],
        ];

        if (!$this->activeKp) {
            $baseSteps[1]['status'] = 'active';
        } else {
            $kpStatus = $this->activeKp->status_pengajuan_kp;
            $seminarStatus = optional($this->activeKp->seminar)->status_seminar;

            $baseSteps[1]['status'] = 'completed';
            $baseSteps[1]['subtext'] = 'Selesai';

            if (in_array($kpStatus, ['Diajukan', 'Proses di Komisi'])) {
                $baseSteps[2]['status'] = 'active';
                $baseSteps[2]['subtext'] = "Status: {$kpStatus}";
            } elseif ($kpStatus === 'Ditolak') {
                $baseSteps[2]['status'] = 'failed';
                $baseSteps[2]['subtext'] = "Status: {$kpStatus}";
            } else {
                $baseSteps[2]['status'] = 'completed';
                $baseSteps[2]['subtext'] = 'Disetujui pada ' . Carbon::parse($this->activeKp->tanggal_disetujui_kp)->format('d/m/Y');
            }

            if ($kpStatus === 'SPK Terbit') {
                $bimbinganVerified = $this->activeKp->konsultasis->where('status_verifikasi', 'Diverifikasi')->count();
                $baseSteps[3]['status'] = 'active';
                $baseSteps[3]['subtext'] = "{$bimbinganVerified} bimbingan terverifikasi.";
            } elseif (in_array($kpStatus, ['Disetujui'])) {
                $baseSteps[3]['status'] = 'upcoming';
                $baseSteps[3]['subtext'] = 'Menunggu SPK Terbit';
            } elseif ($kpStatus !== 'Ditolak' && !in_array($kpStatus, ['Diajukan', 'Proses di Komisi'])) {
                $baseSteps[3]['status'] = 'completed';
            }

            if ($seminarStatus) {
                if(in_array($seminarStatus, ['Diajukan', 'Menunggu Konfirmasi', 'Dijadwalkan'])) {
                    $baseSteps[4]['status'] = 'active';
                    $baseSteps[4]['subtext'] = "Status: {$seminarStatus}";
                } elseif ($seminarStatus === 'Ditolak') {
                    $baseSteps[4]['status'] = 'failed';
                    $baseSteps[4]['subtext'] = "Status: {$seminarStatus}";
                } else {
                    $baseSteps[4]['status'] = 'completed';
                    $baseSteps[4]['subtext'] = 'Seminar telah dinilai';
                }
            }

            if ($seminarStatus === 'Dinilai') {
                $baseSteps[5]['status'] = 'active';
                $baseSteps[5]['subtext'] = 'Nilai Akhir: ' . $this->activeKp->seminar->nilai_akhir;
            }
        }
        $this->steps = $baseSteps;
    }

    // Logika untuk BAPENDIK
    if ($user->role === 'Bapendik') {
        $this->suratBaruCount = SuratPengantar::where('status_surat_pengantar', 'Diajukan')->count();
        $this->kpBaruCount = KerjaPraktek::where('status_pengajuan_kp', 'Diajukan')->count();
        $this->seminarBaruCount = Seminar::where('status_seminar', 'Diajukan')->count();
        $this->spkTerbitCount = KerjaPraktek::where('status_pengajuan_kp', 'Disetujui')->count();
        $this->recentPengajuanKp = KerjaPraktek::with('mahasiswa')->where('status_pengajuan_kp', 'Diajukan')->latest()->take(5)->get();
    }

    // Logika untuk DOSEN KOMISI
    if ($user->role === 'Dosen Komisi' && $user->dosen) {
        $this->validasiKpCount = KerjaPraktek::where('status_pengajuan_kp', 'Proses di Komisi')->count();
        $this->recentValidasiKp = KerjaPraktek::with('mahasiswa')->where('status_pengajuan_kp', 'Proses di Komisi')->latest()->take(5)->get();
    }

    // Logika untuk DOSEN PEMBIMBING
    if ($user->role === 'Dosen Pembimbing' && $user->dosen) {
        $dosenId = $user->dosen->id;
        $this->mahasiswaBimbinganCount = KerjaPraktek::where('dosen_pembimbing_id', $dosenId)->where('status_kp', 'Berlangsung')->count();
        $this->verifikasiBimbinganCount = Konsultasi::where('dosen_pembimbing_id', $dosenId)->where('status_verifikasi', 'Menunggu Verifikasi')->count();
        $this->penilaianKpCount = Seminar::whereHas('kerjaPraktek', fn($q) => $q->where('dosen_pembimbing_id', $dosenId))->where('status_seminar', 'Dijadwalkan')->count();
        $this->recentBimbingan = Konsultasi::with('mahasiswa')->where('dosen_pembimbing_id', $dosenId)->where('status_verifikasi', 'Menunggu Verifikasi')->latest()->take(5)->get();
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
                        <flux:subheading size="lg">Pantau progres Kerja Praktik Anda di bawah ini.</flux:subheading>
                    </div>

                    <flux:card>
                        <h3 class="text-lg font-semibold mb-4 px-2">Progres Kerja Praktik Anda</h3>
                        <div class="space-y-2">
                            @foreach($steps as $number => $step)
                                @php
                                    $isCompleted = $step['status'] === 'completed';
                                    $isActive = $step['status'] === 'active';
                                    $isFailed = $step['status'] === 'failed';
                                @endphp
                                <a href="{{ route($step['href']) }}" class="block p-4 rounded-lg transition-colors {{ $isActive ? 'bg-primary/10 border border-primary' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                    <div class="flex items-start gap-4">
                                        <div class="flex items-center justify-center size-8 rounded-full {{ $isCompleted || $isActive ? 'bg-primary text-primary-foreground' : ($isFailed ? 'bg-danger text-danger-foreground' : 'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400') }}">
                                            @if($isCompleted) <flux:icon name="check" class="size-5" />
                                            @elseif($isFailed) <flux:icon name="x-mark" class="size-5" />
                                            @else <span class="font-bold">{{ $number }}</span>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-semibold">{{ $step['name'] }}</p>
                                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $step['subtext'] ?: $steps[$number]['description'] }}</p>
                                        </div>
                                        @if($isActive)
                                            <flux:icon name="arrow-right" class="size-5 text-primary self-center" />
                                        @endif
                                    </div>
                                </a>
                            @endforeach
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
                        <a href="{{ route('bapendik.surat-pengantar') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="envelope" class="size-8 text-blue-500" /><p class="mt-4 text-3xl font-bold">{{ $suratBaruCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Pengajuan Surat Baru</p></flux:card></a>
                        <a href="{{ route('bapendik.pengajuan-kp') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="document-plus" class="size-8 text-yellow-500" /><p class="mt-4 text-3xl font-bold">{{ $kpBaruCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Review Berkas KP</p></flux:card></a>
                        <a href="{{ route('bapendik.pengajuan-kp', ['tab' => 'penerbitan']) }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="document-check" class="size-8 text-green-500" /><p class="mt-4 text-3xl font-bold">{{ $spkTerbitCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Penerbitan SPK</p></flux:card></a>
                        <a href="{{ route('bapendik.penjadwalan-seminar') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="calendar-days" class="size-8 text-purple-500" /><p class="mt-4 text-3xl font-bold">{{ $seminarBaruCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Pendaftaran Seminar</p></flux:card></a>
                    </div>
                    <flux:card>
                        <h3 class="text-lg font-semibold mb-4">Aktivitas Terbaru: Review Berkas KP</h3>
                        <flux:table>
                            <flux:table.columns><flux:table.column>Nama Mahasiswa</flux:table.column><flux:table.column>Judul KP</flux:table.column></flux:table.columns>
                            <flux:table.rows>
                                @forelse($recentPengajuanKp as $kp)
                                    <flux:table.row><flux:table.cell>{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell><flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell></flux:table.row>
                                @empty
                                    <flux:table.row><flux:table.cell colspan="2" class="text-center">Tidak ada pengajuan baru.</flux:table.cell></flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </div>

                {{-- ================================================================= --}}
                {{-- TAMPILAN DASHBOARD UNTUK DOSEN --}}
                {{-- ================================================================= --}}
            @elseif (in_array(auth()->user()->role, ['Dosen Pembimbing', 'Dosen Komisi']))
                <div class="space-y-8">
                    <flux:heading size="2xl" level="1">Dashboard Dosen</flux:heading>

                    @if(auth()->user()->role === 'Dosen Komisi')
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <a href="{{ route('doskom.validasi-kp') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="document-magnifying-glass" class="size-8 text-blue-500" /><p class="mt-4 text-3xl font-bold">{{ $validasiKpCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Validasi Proposal KP</p></flux:card></a>
                        </div>
                        <flux:card>
                            <h3 class="text-lg font-semibold mb-4">Aktivitas Terbaru: Validasi Proposal</h3>
                            <flux:table>
                                <flux:table.columns><flux:table.column>Nama Mahasiswa</flux:table.column><flux:table.column>Judul KP</flux:table.column></flux:table.columns>
                                <flux:table.rows>
                                    @forelse($recentValidasiKp as $kp)
                                        <flux:table.row><flux:table.cell>{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell><flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell></flux:table.row>
                                    @empty
                                        <flux:table.row><flux:table.cell colspan="2" class="text-center">Tidak ada pengajuan baru.</flux:table.cell></flux:table.row>
                                    @endforelse
                                </flux:table.rows>
                            </flux:table>
                        </flux:card>
                    @endif

                    @if(auth()->user()->role === 'Dosen Pembimbing' || auth()->user()->role === 'Dosen Komisi')
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <a href="{{ route('dospem.mahasiswa') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="user-group" class="size-8 text-blue-500" /><p class="mt-4 text-3xl font-bold">{{ $mahasiswaBimbinganCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Mahasiswa Bimbingan Aktif</p></flux:card></a>
                            <a href="{{ route('dospem.mahasiswa') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="chat-bubble-left-right" class="size-8 text-yellow-500" /><p class="mt-4 text-3xl font-bold">{{ $verifikasiBimbinganCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Verifikasi Bimbingan</p></flux:card></a>
                            <a href="{{ route('dospem.penilaian') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="clipboard-document-check" class="size-8 text-green-500" /><p class="mt-4 text-3xl font-bold">{{ $penilaianKpCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Perlu Dinilai</p></flux:card></a>
                        </div>
                        <flux:card>
                            <h3 class="text-lg font-semibold mb-4">Aktivitas Terbaru: Perlu Diverifikasi</h3>
                            <flux:table>
                                <flux:table.columns><flux:table.column>Nama Mahasiswa</flux:table.column><flux:table.column>Topik Bimbingan</flux:table.column></flux:table.columns>
                                <flux:table.rows>
                                    @forelse($recentBimbingan as $bimbingan)
                                        <flux:table.row><flux:table.cell>{{ $bimbingan->mahasiswa->nama_mahasiswa }}</flux:table.cell><flux:table.cell>{{ Str::limit($bimbingan->topik_konsultasi, 40) }}</flux:table.cell></flux:table.row>
                                    @empty
                                        <flux:table.row><flux:table.cell colspan="2" class="text-center">Tidak ada bimbingan baru.</flux:table.cell></flux:table.row>
                                    @endforelse
                                </flux:table.rows>
                            </flux:table>
                        </flux:card>
                    @endif
                </div>
            @endif
        @endauth
</div>
