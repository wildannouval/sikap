<?php

use App\Models\Distribusi;
use App\Models\Jurusan;
use App\Models\KerjaPraktek;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;
use Flux\DateRange;

new #[Title('Laporan & Arsip KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti untuk state halaman
    #[Url]
    public string $tab = 'kp';
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public ?int $jurusanFilter = null;
    #[Url]
    public string $sortField = 'tanggal_pengajuan_kp'; // <-- Diubah ke tanggal_pengajuan_kp
    #[Url]
    public string $sortDirection = 'desc';

    // PERBAIKAN: Menggunakan DateRange dengan benar
    #[Url]
    public ?DateRange $dateRange = null;

    // Hook untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'jurusanFilter', 'dateRange', 'tab'])) {
            $this->resetPage();
        }
    }

    // Fungsi untuk sorting
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // Computed property untuk mengambil data Jurusan (untuk filter)
    #[Computed]
    public function jurusans()
    {
        return Jurusan::orderBy('nama_jurusan')->get();
    }

    // Computed property untuk TAB 1: Arsip Kerja Praktik
    #[Computed]
    public function arsipKp()
    {
        $user = Auth::user();
        $query = KerjaPraktek::with(['mahasiswa.jurusan', 'dosenPembimbing', 'seminar']);

        if ($user->role === 'Dosen Pembimbing' && $user->dosen) {
            $query->where('dosen_pembimbing_id', $user->dosen->id);
        }

        return $query
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('judul_kp', 'like', '%' . $this->search . '%')
                        ->orWhereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                            ->orWhere('nim', 'like', '%' . $this->search . '%'))
                        ->orWhereHas('dosenPembimbing', fn($q) => $q->where('nama_dosen', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status_kp', $this->statusFilter);
            })
            ->when($this->jurusanFilter, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('jurusan_id', $this->jurusanFilter));
            })
            // PERBAIKAN: Logika filter tanggal disesuaikan dengan DateRange
            ->when($this->dateRange && $this->dateRange->start() && $this->dateRange->end(), function ($query) {
                $query->whereBetween('tanggal_pengajuan_kp', [
                    $this->dateRange->start()->startOfDay(),
                    $this->dateRange->end()->endOfDay()
                ]);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10, ['*'], 'kpPage');
    }

    // Computed property BARU untuk TAB 2: Arsip Laporan Final
    #[Computed]
    public function arsipDistribusi()
    {
        $user = Auth::user();
        $query = Distribusi::with(['mahasiswa', 'kerjaPraktek']);

        // --- LOGIKA BARU: Filter berdasarkan peran ---
        // Jika yang login adalah Dosen Pembimbing (dan bukan Komisi), filter hanya untuk mahasiswa bimbingannya
        if ($user->role === 'Dosen Pembimbing' && $user->dosen) {
            $dosenId = $user->dosen->id;
            $query->whereHas('kerjaPraktek', function ($q) use ($dosenId) {
                $q->where('dosen_pembimbing_id', $dosenId);
            });
        }
        // Untuk Bapendik dan Dosen Komisi, tidak ada filter tambahan, tampilkan semua.

        // Terapkan pencarian
        $query->whereHas('mahasiswa', function ($subq) {
            $subq->when($this->search, function ($q) {
                $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                    ->orWhere('nim', 'like', '%' . $this->search . '%');
            });
        });

        return $query->latest('tanggal_distribusi')->paginate(10, ['*'], 'distribusiPage');
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Laporan & Arsip</flux:heading>
            <flux:subheading size="lg" class="mb-6">Pusat data untuk semua riwayat Kerja Praktik dan Laporan Final.</flux:subheading>
        </div>
        <div>
            {{-- Tombol Ekspor yang sudah dinamis --}}
            <a href="{{ route('laporan.export-kp', [
                'q' => $search,
                'statusFilter' => $statusFilter,
                'jurusanFilter' => $jurusanFilter,
                'startDate' => $dateRange?->start()?->toDateString(),
                'endDate' => $dateRange?->end()?->toDateString()
            ]) }}" target="_blank">
                <flux:button variant="primary" icon="document-arrow-down">Ekspor Data</flux:button>
            </a>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="kp">Arsip Kerja Praktik</flux:tab>
            <flux:tab name="distribusi">Arsip Laporan Final</flux:tab>
        </flux:tabs>

        {{-- Panel untuk Tab "Arsip Kerja Praktik" --}}
        <flux:tab.panel name="kp">
            <flux:card class="mt-4">
                {{-- Header Card dengan Search & Filter --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
                    <div class="flex-1 w-full">
                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari mhs, nim, dospem, atau judul..." icon="magnifying-glass" />
                    </div>
                    <div class="flex w-full sm:w-auto gap-2">
                        <flux:select wire:model.live="jurusanFilter" class="w-full sm:w-48" placeholder="Filter Jurusan">
                            <option value="">Semua Jurusan</option>
                            @foreach($this->jurusans as $jurusan)
                                <option value="{{ $jurusan->id }}">{{ $jurusan->nama_jurusan }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model.live="statusFilter" class="w-full sm:w-48" placeholder="Filter Status KP">
                            <option value="">Semua Status KP</option>
                            <option value="Berlangsung">Berlangsung</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Batal">Batal</option>
                        </flux:select>
                        <flux:date-picker wire:model.live="dateRange" mode="range" class="w-full sm:w-64" with-presets clearable />
                    </div>
                </div>

                <flux:table :paginate="$this->arsipKp">
                    <flux:table.columns>
                        <flux:table.column>Mahasiswa</flux:table.column>
                        {{-- KOLOM BARU --}}
                        <flux:table.column sortable :sorted="$this->sortField === 'tanggal_pengajuan_kp'" :direction="$this->sortDirection" wire:click="sortBy('tanggal_pengajuan_kp')">Tgl. Pengajuan</flux:table.column>
                        <flux:table.column>Dosen Pembimbing</flux:table.column>
                        <flux:table.column>Status KP</flux:table.column>
                        <flux:table.column>Nilai Akhir</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->arsipKp as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">
                                    {{ $kp->mahasiswa->nama_mahasiswa }}
                                    <span class="block text-xs font-normal text-zinc-500">{{ $kp->mahasiswa->nim }}</span>
                                </flux:table.cell>
                                {{-- DATA BARU --}}
                                <flux:table.cell>{{ \Carbon\Carbon::parse($kp->tanggal_pengajuan_kp)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $kp->dosenPembimbing->nama_dosen ?? '-' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($kp->status_kp)
                                        <flux:badge :color="match($kp->status_kp) {'Berlangsung' => 'blue', 'Selesai' => 'green', 'Batal' => 'red', default => 'zinc'}" size="sm">{{ $kp->status_kp }}</flux:badge>
                                    @else
                                        <span class="text-zinc-500 italic">{{$kp->status_pengajuan_kp}}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($kp->seminar?->nilai_akhir)
                                        <flux:badge color="primary" size="sm">{{ $kp->seminar->nilai_akhir }}</flux:badge>
                                    @else - @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5" class="text-center">Tidak ada data ditemukan.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>

        {{-- Panel untuk Tab "Arsip Laporan Final" --}}
        <flux:tab.panel name="distribusi">
            <flux:card class="mt-4">
                <div class="p-4 border-b dark:border-neutral-700">
                    <flux:input wire:model.live.debounce.300ms="search"
                                placeholder="Cari berdasarkan nama atau NIM mahasiswa..." icon="magnifying-glass"/>
                </div>

                <flux:table :paginate="$this->arsipDistribusi">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>NIM</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Tanggal Upload</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->arsipDistribusi as $distribusi)
                            <flux:table.row :key="$distribusi->id">
                                <flux:table.cell
                                    variant="strong">{{ $distribusi->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ $distribusi->mahasiswa->nim }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($distribusi->kerjaPraktek->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($distribusi->tanggal_distribusi)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button as="a" href="{{ asset('storage/' . $distribusi->berkas_distribusi) }}"
                                                 target="_blank" size="xs" icon="document-arrow-down">
                                        Unduh Berkas
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center">Tidak ada data distribusi ditemukan.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>
</div>
