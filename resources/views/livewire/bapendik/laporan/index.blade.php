<?php

use App\Models\Jurusan;
use App\Models\KerjaPraktek;
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
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public ?int $jurusanFilter = null;
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Ganti startDate dan endDate dengan satu properti DateRange
    public ?DateRange $dateRange = null; // Jadikan nullable dan beri nilai awal null

//    // Inisialisasi nilai awal untuk properti
//    public function mount(): void
//    {
//        // Membuat objek DateRange baru dengan nilai awal null (tanpa batas)
//        $this->dateRange = new DateRange(null, null);
//    }

    // Hook untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'jurusanFilter', 'dateRange'])) {
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

    // Computed property utama untuk mengambil semua data KP dengan filter
    #[Computed]
    public function arsipKp()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan', 'dosenPembimbing', 'seminar'])
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
            // Logika BARU untuk filter rentang tanggal menggunakan DateRange
            ->when($this->dateRange, function ($query) { // Cek apakah $dateRange ada isinya
                if ($this->dateRange->start() && $this->dateRange->end()) {
                    $query->whereBetween('tanggal_pengajuan_kp', [
                        $this->dateRange->start()->startOfDay(),
                        $this->dateRange->end()->endOfDay()
                    ]);
                }
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Laporan & Arsip Kerja Praktik</flux:heading>
            <flux:subheading size="lg" class="mb-6">Lihat, cari, dan filter semua data riwayat Kerja Praktik.</flux:subheading>
        </div>
        <div>
            {{-- Tombol Ekspor akan kita fungsikan nanti --}}
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

    {{-- Tabel Laporan & Arsip --}}
    <flux:card class="mt-8">
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
                {{-- GANTI DUA INPUT TANGGAL DENGAN SATU DATE PICKER INI --}}
                <flux:date-picker
                    wire:model.live="dateRange"
                    mode="range"
                    class="w-full sm:w-64"
                    with-presets
                    clearable
                />
            </div>
        </div>

        <flux:table :paginate="$this->arsipKp">
            <flux:table.columns>
                <flux:table.column>Mahasiswa</flux:table.column>
                <flux:table.column>Judul KP</flux:table.column>
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
                        <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                        <flux:table.cell>{{ $kp->dosenPembimbing->nama_dosen ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($kp->status_kp)
                                @php
                                    $color = match($kp->status_kp) {
                                        'Berlangsung' => 'blue',
                                        'Selesai' => 'green',
                                        'Batal' => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$color" size="sm">{{ $kp->status_kp }}</flux:badge>
                            @else
                                <span class="text-zinc-500 italic">Belum dimulai</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($kp->seminar?->nilai_akhir)
                                <flux:badge color="primary" size="sm">{{ $kp->seminar->nilai_akhir }}</flux:badge>
                            @else
                                -
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">Tidak ada data ditemukan.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
