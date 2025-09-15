<?php

use App\Models\KerjaPraktek;
use App\Models\Konsultasi;
use App\Models\Seminar;
use App\Models\SuratPengantar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use function Livewire\Volt\{layout, title, state, mount};
use Carbon\Carbon;
use Livewire\Attributes\Title;

// --- ATRIBUT BARU UNTUK MENGATUR LAYOUT DAN JUDUL ---
layout('components.layouts.app');
title('Dashboard');

// --- STATE MANAGEMENT ---
state([
    // Mahasiswa
    'activeKp' => null,
    'steps' => [],
    'suratDisetujui' => false,
    'nextAction' => null,
    'hasStartedProcess' => false,
    // Bapendik
    'suratBaruCount' => 0,
    'kpBaruCount' => 0,
    'seminarBaruCount' => 0,
    'spkTerbitCount' => 0,
    'prioritizedKpi' => null,
    'bapendikChartData' => [],
    'unifiedFeed' => collect(), // unifiedFeed dipindahkan ke sini agar konsisten
    // Dosen
    'validasiKpCount' => 0,
    'mahasiswaBimbinganCount' => 0,
    'verifikasiBimbinganCount' => 0,
    'penilaianKpCount' => 0,
    'mahasiswaBimbinganList' => collect(),
    'dosenChartData' => [],
]);

// --- LOGIC ---
mount(function () {
    $user = Auth::user();

    if ($user->role === 'Mahasiswa' && $user->mahasiswa) {
        $mahasiswaId = $user->mahasiswa->id;
        $this->activeKp = KerjaPraktek::with(['seminar', 'konsultasis', 'dosenPembimbing'])->where('mahasiswa_id', $mahasiswaId)->latest()->first();
        $hasAnySurat = SuratPengantar::where('mahasiswa_id', $mahasiswaId)->exists();
        $this->suratDisetujui = SuratPengantar::where('mahasiswa_id', $mahasiswaId)
            ->whereIn('status_surat_pengantar', ['Disetujui', 'Siap Diambil', 'Selesai'])
            ->exists();
        $this->hasStartedProcess = $this->activeKp || $hasAnySurat;
        $baseSteps = [
            1 => ['name' => 'Surat Pengantar', 'status' => 'upcoming', 'subtext' => 'Membuat surat pengantar untuk perusahaan.', 'href' => 'surat-pengantar.index'],
            2 => ['name' => 'Pengajuan KP', 'status' => 'upcoming', 'subtext' => 'Mengajukan proposal dan surat penerimaan.', 'href' => 'kp.pengajuan'],
            3 => ['name' => 'Bimbingan', 'status' => 'upcoming', 'subtext' => 'Melakukan bimbingan dengan dosen pembimbing.', 'href' => 'kp.bimbingan'],
            4 => ['name' => 'Seminar', 'status' => 'upcoming', 'subtext' => 'Mendaftar dan melaksanakan seminar.', 'href' => 'seminar.pendaftaran'],
            5 => ['name' => 'Selesai', 'status' => 'upcoming', 'subtext' => 'Melihat nilai akhir dan menyelesaikan administrasi.', 'href' => 'kp.nilai'],
        ];
        if ($this->activeKp) {
            $baseSteps[1]['status'] = 'completed';
            $baseSteps[1]['subtext'] = 'Selesai';
            $kpStatus = $this->activeKp->status_pengajuan_kp;
            $seminarStatus = optional($this->activeKp->seminar)->status_seminar;
            if (in_array($kpStatus, ['Diajukan', 'Proses di Komisi'])) {
                $baseSteps[2]['status'] = 'active';
                $baseSteps[2]['subtext'] = "Status: {$kpStatus}";
            } elseif ($kpStatus === 'Ditolak') {
                $baseSteps[2]['status'] = 'failed';
                $baseSteps[2]['subtext'] = "Status: {$kpStatus}";
            } else {
                $baseSteps[2]['status'] = 'completed';
                if ($this->activeKp->tanggal_disetujui_kp) {
                    $baseSteps[2]['subtext'] = 'Disetujui pada ' . Carbon::parse($this->activeKp->tanggal_disetujui_kp)->format('d/m/Y');
                }
            }
            if ($kpStatus === 'SPK Terbit' && (!$seminarStatus || in_array($seminarStatus, ['Diajukan', 'Ditolak', 'Menunggu Konfirmasi']))) {
                $bimbinganVerified = $this->activeKp->konsultasis->where('status_verifikasi', 'Diverifikasi')->count();
                $baseSteps[3]['status'] = 'active';
                $baseSteps[3]['subtext'] = "{$bimbinganVerified} bimbingan terverifikasi.";
            } elseif (in_array($kpStatus, ['Disetujui'])) {
                $baseSteps[3]['status'] = 'upcoming';
                $baseSteps[3]['subtext'] = 'Menunggu SPK Terbit';
            } elseif ($seminarStatus && in_array($seminarStatus, ['Dijadwalkan', 'Selesai', 'Dinilai'])) {
                $baseSteps[3]['status'] = 'completed';
            }
            if ($seminarStatus) {
                if (in_array($seminarStatus, ['Dijadwalkan', 'Selesai'])) {
                    $baseSteps[4]['status'] = 'active';
                    $baseSteps[4]['subtext'] = "Status: {$seminarStatus}";
                } elseif ($seminarStatus === 'Ditolak') {
                    $baseSteps[4]['status'] = 'failed';
                    $baseSteps[4]['subtext'] = "Status: {$seminarStatus}";
                } elseif ($seminarStatus === 'Dinilai') {
                    $baseSteps[4]['status'] = 'completed';
                    $baseSteps[4]['subtext'] = 'Seminar telah dinilai';
                }
            }
            if ($seminarStatus === 'Dinilai') {
                $baseSteps[5]['status'] = 'active';
                $baseSteps[5]['subtext'] = 'Nilai Akhir: ' . optional($this->activeKp->seminar)->nilai_akhir;
            }
        } elseif ($this->suratDisetujui) {
            $baseSteps[1]['status'] = 'completed';
            $baseSteps[1]['subtext'] = 'Selesai';
            $baseSteps[2]['status'] = 'active';
        } else {
            if ($hasAnySurat) {
                $baseSteps[1]['status'] = 'active';
            }
        }
        $this->steps = $baseSteps;
        $this->nextAction = null;
        foreach ($this->steps as $step) {
            if ($step['status'] === 'active' || $step['status'] === 'failed') {
                $this->nextAction = [
                    'title' => ($step['status'] === 'failed' ? 'Perbaiki ' : 'Langkah Selanjutnya: ') . $step['name'],
                    'description' => ($step['status'] === 'failed' ? 'Terdapat masalah pada tahap ini. Silakan periksa dan ajukan kembali.' : "Anda saat ini berada pada tahap {$step['name']}. Klik tombol di bawah untuk melanjutkan."),
                    'button_text' => ($step['status'] === 'failed' ? 'Perbaiki Sekarang' : 'Lanjutkan Proses'),
                    'href' => $step['href'],
                ];
                break;
            }
        }
    } 
    elseif ($user->role === 'Bapendik') {
        $this->suratBaruCount = SuratPengantar::where('status_surat_pengantar', 'Diajukan')->count();
        $this->kpBaruCount = KerjaPraktek::where('status_pengajuan_kp', 'Diajukan')->count();
        $this->seminarBaruCount = Seminar::where('status_seminar', 'Diajukan')->count();
        $this->spkTerbitCount = KerjaPraktek::where('status_pengajuan_kp', 'Disetujui')->count();
        $kpiCounts = [
            'surat' => $this->suratBaruCount,
            'kp' => $this->kpBaruCount,
            'seminar' => $this->seminarBaruCount,
        ];
        if(max($kpiCounts) > 0) {
            $this->prioritizedKpi = array_keys($kpiCounts, max($kpiCounts))[0];
        }
        $feedItems = collect();
        $suratItems = SuratPengantar::with('mahasiswa')->where('status_surat_pengantar', 'Diajukan')->latest('tanggal_pengajuan_surat_pengantar')->take(5)->get()->map(function($item) {
            return (object) [
                'type' => 'surat_baru',
                'title' => $item->mahasiswa->nama_mahasiswa,
                'description' => 'Mengajukan surat pengantar ke ' . $item->lokasi_surat_pengantar,
                'timestamp' => $item->tanggal_pengajuan_surat_pengantar,
                'href' => route('bapendik.surat-pengantar'),
            ];
        });
        $feedItems = $feedItems->merge($suratItems);
        $kpItems = KerjaPraktek::with('mahasiswa')->where('status_pengajuan_kp', 'Diajukan')->latest('tanggal_pengajuan_kp')->take(5)->get()->map(function($item) {
            return (object) [
                'type' => 'kp_baru',
                'title' => $item->mahasiswa->nama_mahasiswa,
                'description' => 'Mengajukan berkas KP: ' . Str::limit($item->judul_kp, 35),
                'timestamp' => $item->tanggal_pengajuan_kp,
                'href' => route('bapendik.pengajuan-kp'),
            ];
        });
        $feedItems = $feedItems->merge($kpItems);
        $seminarItems = Seminar::with('kerjaPraktek.mahasiswa')->where('status_seminar', 'Diajukan')->latest()->take(5)->get()->map(function($item) {
            return (object) [
                'type' => 'seminar_baru',
                'title' => $item->kerjaPraktek->mahasiswa->nama_mahasiswa,
                'description' => 'Mendaftar untuk jadwal seminar KP',
                'timestamp' => $item->created_at,
                'href' => route('bapendik.penjadwalan-seminar'),
            ];
        });
        $feedItems = $feedItems->merge($seminarItems);
        $this->unifiedFeed = $feedItems->sortByDesc('timestamp')->take(5);

        $endDate = Carbon::now();
        $startDate = Carbon::now()->subMonths(2)->startOfMonth();
        $monthsTemplate = collect();
        for ($date = $startDate->copy(); $date->lessThanOrEqualTo($endDate); $date->addMonth()) {
            $monthsTemplate->put($date->format('Y-m'), ['month' => $date->isoFormat('MMMM')]);
        }

        $suratData = SuratPengantar::whereBetween('tanggal_pengajuan_surat_pengantar', [$startDate, $endDate])->get()->groupBy(fn($date) => Carbon::parse($date->tanggal_pengajuan_surat_pengantar)->format('Y-m'));
        $kpData = KerjaPraktek::whereBetween('tanggal_pengajuan_kp', [$startDate, $endDate])->get()->groupBy(fn($date) => Carbon::parse($date->tanggal_pengajuan_kp)->format('Y-m'));
        $seminarData = Seminar::whereBetween('created_at', [$startDate, $endDate])->get()->groupBy(fn($date) => Carbon::parse($date->created_at)->format('Y-m'));
        
        $months = $monthsTemplate->map(function ($monthData, $key) use ($suratData, $kpData, $seminarData) {
            return [
                'month' => $monthData['month'],
                'surat' => $suratData->has($key) ? $suratData->get($key)->count() : 0,
                'kp' => $kpData->has($key) ? $kpData->get($key)->count() : 0,
                'seminar' => $seminarData->has($key) ? $seminarData->get($key)->count() : 0,
            ];
        });

        $this->bapendikChartData = [
            'categories' => $months->pluck('month')->all(),
            'series' => [
                ['name' => 'Surat Pengantar', 'data' => $months->pluck('surat')->all()],
                ['name' => 'Pengajuan KP', 'data' => $months->pluck('kp')->all()],
                ['name' => 'Pendaftaran Seminar', 'data' => $months->pluck('seminar')->all()],
            ]
        ];

    } 
    elseif (in_array($user->role, ['Dosen Komisi', 'Dosen Pembimbing']) && $user->dosen) {
        $feedItems = collect();
        if ($user->role === 'Dosen Komisi') {
            $this->validasiKpCount = KerjaPraktek::where('status_pengajuan_kp', 'Proses di Komisi')->count();
            $validasiItems = KerjaPraktek::with('mahasiswa')
                ->where('status_pengajuan_kp', 'Proses di Komisi')
                ->latest('tanggal_pengajuan_kp')
                ->get()
                ->map(function ($kp) {
                    return (object) [
                        'type' => 'validasi_kp',
                        'title' => $kp->mahasiswa->nama_mahasiswa,
                        'description' => 'Mengajukan validasi proposal KP: ' . Str::limit($kp->judul_kp, 30),
                        'timestamp' => $kp->tanggal_pengajuan_kp,
                        'href' => route('doskom.validasi-kp'),
                    ];
                });
            $feedItems = $feedItems->merge($validasiItems);
        }
        $this->mahasiswaBimbinganCount = KerjaPraktek::where('dosen_pembimbing_id', $user->dosen->id)->where('status_kp', 'Berlangsung')->count();
        $this->verifikasiBimbinganCount = Konsultasi::where('dosen_pembimbing_id', $user->dosen->id)->where('status_verifikasi', 'Menunggu Verifikasi')->count();
        $this->penilaianKpCount = Seminar::whereHas('kerjaPraktek', fn($q) => $q->where('dosen_pembimbing_id', $user->dosen->id))->whereIn('status_seminar', ['Dijadwalkan', 'Selesai'])->count();
        $bimbinganItems = Konsultasi::with('mahasiswa')
            ->where('dosen_pembimbing_id', $user->dosen->id)
            ->where('status_verifikasi', 'Menunggu Verifikasi')
            ->latest()
            ->get()
            ->map(function ($k) {
                return (object) [
                    'type' => 'verifikasi_bimbingan',
                    'title' => $k->mahasiswa->nama_mahasiswa,
                    'description' => 'Mengajukan verifikasi bimbingan: ' . Str::limit($k->topik_konsultasi, 30),
                    'timestamp' => $k->created_at,
                    'href' => route('dospem.mahasiswa'),
                ];
            });
        $feedItems = $feedItems->merge($bimbinganItems);
        $penilaianItems = Seminar::with('kerjaPraktek.mahasiswa')
            ->whereHas('kerjaPraktek', fn($q) => $q->where('dosen_pembimbing_id', $user->dosen->id))
            ->whereIn('status_seminar', ['Dijadwalkan', 'Selesai'])
            ->latest('tanggal_seminar')
            ->get()
            ->map(function ($s) {
                return (object) [
                    'type' => 'penilaian_seminar',
                    'title' => $s->kerjaPraktek->mahasiswa->nama_mahasiswa,
                    'description' => 'Perlu penilaian seminar (' . $s->status_seminar . ')',
                    'timestamp' => $s->tanggal_seminar,
                    'href' => route('dospem.penilaian'),
                ];
            });
        $feedItems = $feedItems->merge($penilaianItems);
        $this->unifiedFeed = $feedItems->sortByDesc('timestamp')->take(5);
        $this->mahasiswaBimbinganList = KerjaPraktek::with('mahasiswa', 'seminar', 'konsultasis')
            ->where('dosen_pembimbing_id', $user->dosen->id)
            ->where('status_kp', 'Berlangsung')
            ->get();
        $statusCounts = [
            'Proses Bimbingan' => 0,
            'Mengajukan Seminar' => 0,
            'Menunggu Penilaian' => 0,
        ];
        foreach ($this->mahasiswaBimbinganList as $kp) {
            $statusText = 'Proses Bimbingan';
            if ($kp->seminar) {
                if (in_array($kp->seminar->status_seminar, ['Dijadwalkan', 'Selesai'])) {
                    $statusText = 'Menunggu Penilaian';
                } elseif ($kp->seminar->status_seminar === 'Diajukan') {
                    $statusText = 'Mengajukan Seminar';
                }
            }
            $statusCounts[$statusText]++;
        }
        $this->dosenChartData = [
            'labels' => array_keys($statusCounts),
            'series' => array_values($statusCounts),
        ];
    }
});

?>

<div>
    @auth
        @if (auth()->user()->role === 'Mahasiswa')
            <div class="space-y-8">
                <flux:heading size="xl" level="1">Selamat Datang, {{ auth()->user()->name }}!</flux:heading>
                
                @if ($hasStartedProcess)
                    <flux:subheading size="lg">Pantau progres dan kelola Kerja Praktik Anda di sini.</flux:subheading>
                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">
                        <div class="lg:col-span-2 space-y-8">
                            @if ($nextAction)
                            <flux:card class="border-2 border-primary dark:border-primary-700 bg-primary-50 dark:bg-primary-900/10">
                                <div class="flex flex-col items-start gap-4">
                                    <div class="flex items-center gap-3">
                                        <flux:icon name="sparkles" class="size-6 text-primary" />
                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $nextAction['title'] }}</h3>
                                    </div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $nextAction['description'] }}</p>
                                    <a href="{{ route($nextAction['href']) }}" class="mt-2 inline-flex items-center justify-center px-4 py-2 text-sm font-medium tracking-tight transition-colors border rounded-md shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none bg-primary text-primary-foreground hover:bg-primary/90">
                                        {{ $nextAction['button_text'] }}
                                    </a>
                                </div>
                            </flux:card>
                            @endif
                            @if ($activeKp)
                            <flux:card>
                                <h3 class="text-lg font-semibold mb-4">Informasi KP Aktif</h3>
                                <div class="space-y-4 text-sm">
                                    <div class="flex justify-between items-start gap-2">
                                        <span class="text-zinc-500 dark:text-zinc-400">Judul KP:</span>
                                        <span class="font-medium text-right">{{ $activeKp->judul_kp }}</span>
                                    </div>
                                    <hr class="dark:border-zinc-700"/>
                                    <div class="flex justify-between items-center gap-2">
                                        <span class="text-zinc-500 dark:text-zinc-400">Lokasi:</span>
                                        <span class="font-medium">{{ $activeKp->lokasi_kp ?? '-' }}</span>
                                    </div>
                                    <hr class="dark:border-zinc-700"/>
                                    <div class="flex justify-between items-center gap-2">
                                        <span class="text-zinc-500 dark:text-zinc-400">Pembimbing:</span>
                                        <span class="font-medium">{{ optional($activeKp->dosenPembimbing)->nama_dosen ?? 'Belum Ditentukan' }}</span>
                                    </div>
                                    <hr class="dark:border-zinc-700"/>
                                    <div class="flex justify-between items-center gap-2">
                                        <span class="text-zinc-500 dark:text-zinc-400">Periode:</span>
                                        <span class="font-medium">
                                            {{ $activeKp->tanggal_mulai ? Carbon::parse($activeKp->tanggal_mulai)->isoFormat('D MMM Y') : 'N/A' }} -
                                            {{ $activeKp->tanggal_selesai ? Carbon::parse($activeKp->tanggal_selesai)->isoFormat('D MMM Y') : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </flux:card>
                            @endif
                        </div>
                        <div class="lg:col-span-3">
                            <flux:card>
                                <h3 class="text-lg font-semibold mb-4 px-2">Progres Kerja Praktik Anda</h3>
                                <div class="space-y-2">
                                    @foreach($steps as $number => $step)
                                        @php
                                            $isCompleted = $step['status'] === 'completed';
                                            $isActive = $step['status'] === 'active';
                                            $isFailed = $step['status'] === 'failed';
                                            $isUpcoming = $step['status'] === 'upcoming';
                                        @endphp
                                        <a href="{{ route($step['href']) }}" class="block p-4 rounded-lg transition-colors
                                        {{ $isActive ? 'bg-primary-50 dark:bg-primary-900/20 border border-primary-300 dark:border-primary-700' : '' }}
                                        {{ !$isActive ? 'hover:bg-zinc-50 dark:hover:bg-zinc-800' : ''}}">
                                            <div class="flex items-start gap-4">
                                                <div class="flex items-center justify-center size-8 rounded-full font-bold
                                                    {{ $isCompleted ? 'bg-green-500 text-white' : '' }}
                                                    {{ $isActive ? 'bg-primary text-primary-foreground' : '' }}
                                                    {{ $isFailed ? 'bg-danger text-danger-foreground' : '' }}
                                                    {{ $isUpcoming ? 'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400' : '' }}
                                                ">
                                                    @if($isCompleted) <flux:icon name="check" class="size-5" />
                                                    @elseif($isFailed) <flux:icon name="x-mark" class="size-5" />
                                                    @else <span>{{ $number }}</span>
                                                    @endif
                                                </div>
                                                <div class="flex-1">
                                                    <p class="font-semibold">{{ $step['name'] }}</p>
                                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $step['subtext'] }}</p>
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
                    </div>
                @else
                    <flux:card class="mt-4 text-center">
                        <div class="p-6">
                            <flux:icon name="academic-cap" class="size-12 mx-auto text-primary" />
                            <h2 class="mt-4 text-xl font-semibold">Mulai Proses Kerja Praktik Anda!</h2>
                            <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                                Selamat datang di SIKAP! Langkah pertama untuk memulai Kerja Praktik adalah dengan mengajukan Surat Pengantar yang ditujukan untuk perusahaan impian Anda.
                            </p>
                            <a href="{{ route('surat-pengantar.index') }}" class="mt-6 inline-flex items-center justify-center px-6 py-3 text-sm font-medium tracking-tight transition-colors border rounded-md shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none bg-primary text-primary-foreground hover:bg-primary/90">
                                Ajukan Surat Pengantar Sekarang
                            </a>
                        </div>
                    </flux:card>
                @endif
            </div>
        
        @elseif (auth()->user()->role === 'Bapendik')
            <div class="space-y-8">
                <flux:heading size="xl" level="1">Dashboard Bapendik</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="{{ route('bapendik.surat-pengantar') }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full {{ $prioritizedKpi === 'surat' ? 'border-2 border-primary' : '' }}">
                            <flux:icon name="envelope" class="size-8 text-blue-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $suratBaruCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pengajuan Surat Baru</p>
                        </flux:card>
                    </a>
                    <a href="{{ route('bapendik.pengajuan-kp') }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full {{ $prioritizedKpi === 'kp' ? 'border-2 border-primary' : '' }}">
                            <flux:icon name="document-plus" class="size-8 text-yellow-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $kpBaruCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Review Berkas KP</p>
                        </flux:card>
                    </a>
                    <a href="{{ route('bapendik.pengajuan-kp', ['tab' => 'penerbitan']) }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full">
                            <flux:icon name="document-check" class="size-8 text-green-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $spkTerbitCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Penerbitan SPK</p>
                        </flux:card>
                    </a>
                    <a href="{{ route('bapendik.penjadwalan-seminar') }}" class="block hover:scale-[1.02] transition-transform">
                        <flux:card class="h-full {{ $prioritizedKpi === 'seminar' ? 'border-2 border-primary' : '' }}">
                            <flux:icon name="calendar-days" class="size-8 text-purple-500" />
                            <p class="mt-4 text-3xl font-bold">{{ $seminarBaruCount }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Pendaftaran Seminar</p>
                        </flux:card>
                    </a>
                </div>
                
                {{-- [FIX] Memindahkan inisialisasi grafik ke tag <script> terpisah --}}
                <flux:card>
                    <h3 class="text-lg font-semibold mb-4">Tren Aktivitas Mahasiswa (3 Bulan Terakhir)</h3>
                    <div id="bapendikChart"></div>
                </flux:card>
                <script>
                    document.addEventListener('livewire:navigated', () => {
                        const bapendikChartData = @json($bapendikChartData);
                        const bapendikOptions = {
                            series: bapendikChartData.series,
                            chart: { type: 'bar', height: 350, stacked: true, toolbar: { show: false } },
                            plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
                            dataLabels: { enabled: false },
                            stroke: { show: true, width: 2, colors: ['transparent'] },
                            xaxis: { categories: bapendikChartData.categories },
                            yaxis: { title: { text: 'Jumlah Pengajuan' } },
                            fill: { opacity: 1 },
                            tooltip: { y: { formatter: function (val) { return val + ' pengajuan' } } }
                        };
                        const bapendikChart = new ApexCharts(document.querySelector("#bapendikChart"), bapendikOptions);
                        bapendikChart.render();
                    });
                </script>

                <flux:card>
                    <h3 class="text-lg font-semibold mb-4">Aktivitas Terbaru</h3>
                    <div class="space-y-4">
                        @forelse($unifiedFeed as $item)
                            <a href="{{ $item->href }}" class="block p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 size-8 flex items-center justify-center rounded-full
                                        {{ $item->type === 'surat_baru' ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400' : '' }}
                                        {{ $item->type === 'kp_baru' ? 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/50 dark:text-yellow-400' : '' }}
                                        {{ $item->type === 'seminar_baru' ? 'bg-purple-100 text-purple-600 dark:bg-purple-900/50 dark:text-purple-400' : '' }}
                                    ">
                                        @if($item->type === 'surat_baru') <flux:icon name="envelope" class="size-5" />
                                        @elseif($item->type === 'kp_baru') <flux:icon name="document-plus" class="size-5" />
                                        @elseif($item->type === 'seminar_baru') <flux:icon name="calendar-days" class="size-5" />
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm">{{ $item->title }}</p>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $item->description }}</p>
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">{{ Carbon::parse($item->timestamp)->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="text-center text-zinc-500 p-6">
                                <flux:icon name="check-badge" class="size-10 mx-auto mb-3 text-green-500" />
                                <h3 class="font-semibold">Semua tugas selesai!</h3>
                                <p class="text-sm">Tidak ada aktivitas baru dari mahasiswa saat ini.</p>
                            </div>
                        @endforelse
                    </div>
                </flux:card>
            </div>

        @elseif (in_array(auth()->user()->role, ['Dosen Pembimbing', 'Dosen Komisi']))
    <div class="space-y-8">
        <flux:heading size="xl" level="1">Dashboard Dosen</flux:heading>

        {{-- [START] PERUBAHAN LAYOUT --}}
        
        {{-- Bagian Atas: Kartu KPI --}}
        <div class="space-y-8">
            @if(auth()->user()->role === 'Dosen Komisi')
                <div>
                    <flux:heading size="lg" level="2">Tugas Dosen Komisi</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-4">
                        <a href="{{ route('doskom.validasi-kp') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="document-magnifying-glass" class="size-8 text-blue-500" /><p class="mt-4 text-3xl font-bold">{{ $validasiKpCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Validasi Proposal KP</p></flux:card></a>
                    </div>
                </div>
            @endif
            <div>
                <flux:heading size="lg" level="2">Tugas Dosen Pembimbing</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-4">
                    <a href="{{ route('dospem.mahasiswa') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="user-group" class="size-8 text-blue-500" /><p class="mt-4 text-3xl font-bold">{{ $mahasiswaBimbinganCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Mahasiswa Bimbingan Aktif</p></flux:card></a>
                    <a href="{{ route('dospem.mahasiswa') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="chat-bubble-left-right" class="size-8 text-yellow-500" /><p class="mt-4 text-3xl font-bold">{{ $verifikasiBimbinganCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Verifikasi Bimbingan</p></flux:card></a>
                    <a href="{{ route('dospem.penilaian') }}" class="block hover:scale-[1.02] transition-transform"><flux:card class="h-full"><flux:icon name="clipboard-document-check" class="size-8 text-green-500" /><p class="mt-4 text-3xl font-bold">{{ $penilaianKpCount }}</p><p class="text-sm text-zinc-600 dark:text-zinc-400">Perlu Dinilai</p></flux:card></a>
                </div>
            </div>
        </div>

        {{-- Bagian Bawah: Feed dan Grafik --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start pt-8 border-t dark:border-zinc-700">
            <div class="lg:col-span-2 space-y-8">
                <flux:card>
                    <h3 class="text-lg font-semibold mb-4">Aktivitas & Tugas Terbaru</h3>
                    <div class="space-y-4">
                        @forelse($unifiedFeed as $item)
                            <a href="{{ $item->href }}" class="block p-3 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 size-8 flex items-center justify-center rounded-full
                                        {{ $item->type === 'validasi_kp' ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400' : '' }}
                                        {{ $item->type === 'verifikasi_bimbingan' ? 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/50 dark:text-yellow-400' : '' }}
                                        {{ $item->type === 'penilaian_seminar' || $item->type === 'penelitian_seminar' ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400' : '' }}
                                    ">
                                        @if($item->type === 'validasi_kp') <flux:icon name="document-magnifying-glass" class="size-5" />
                                        @elseif($item->type === 'verifikasi_bimbingan') <flux:icon name="chat-bubble-left-right" class="size-5" />
                                        @elseif($item->type === 'penilaian_seminar' || $item->type === 'penelitian_seminar') <flux:icon name="clipboard-document-check" class="size-5" />
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm">{{ $item->title }}</p>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $item->description }}</p>
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">{{ Carbon::parse($item->timestamp)->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="text-center text-zinc-500 p-6">
                                <flux:icon name="check-badge" class="size-10 mx-auto mb-3 text-green-500" />
                                <h3 class="font-semibold">Semua tugas selesai!</h3>
                                <p class="text-sm">Tidak ada tugas atau notifikasi baru saat ini.</p>
                            </div>
                        @endforelse
                    </div>
                </flux:card>
            </div>
            <div class="lg:col-span-1 space-y-8">
                @if ($mahasiswaBimbinganList->isNotEmpty())
                    <flux:card>
                        <h3 class="text-lg font-semibold mb-4">Distribusi Status Mahasiswa</h3>
                        <div id="dosenChart"></div>
                    </flux:card>
                    <script>
                        document.addEventListener('livewire:navigated', () => {
                            const dosenChartData = @json($dosenChartData);
                            if (dosenChartData && dosenChartData.series.some(s => s > 0)) {
                                const dosenOptions = {
                                    series: dosenChartData.series,
                                    chart: { type: 'donut', height: 350 },
                                    labels: dosenChartData.labels,
                                    responsive: [{
                                        breakpoint: 480,
                                        options: { chart: { width: 200 }, legend: { position: 'bottom' } }
                                    }]
                                };
                                // Hindari merender ulang chart jika sudah ada
                                if (document.querySelector("#dosenChart")._chart) {
                                    document.querySelector("#dosenChart")._chart.updateOptions(dosenOptions);
                                } else {
                                    const dosenChart = new ApexCharts(document.querySelector("#dosenChart"), dosenOptions);
                                    dosenChart.render();
                                }
                            }
                        });
                    </script>
                @endif
            </div>
        </div>

        {{-- [END] PERUBAHAN LAYOUT --}}
    </div>
@endif
    @endauth
</div>