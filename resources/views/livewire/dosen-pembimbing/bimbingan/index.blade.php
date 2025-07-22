<?php

use App\Models\KerjaPraktek;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Title('Mahasiswa Bimbingan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    // Properti BARU untuk Search, Filter, Sort
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Hook BARU untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter'])) {
            $this->resetPage();
        }
    }

    // Fungsi BARU untuk sorting
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function mahasiswaBimbingan()
    {
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) {
            return KerjaPraktek::where('id', -1)->paginate(10);
        }

        return KerjaPraktek::with('mahasiswa')
            ->where('dosen_pembimbing_id', $dosenId)
            ->where('status_pengajuan_kp', 'SPK Terbit')
            ->when($this->search, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                    ->orWhere('nim', 'like', '%' . $this->search . '%'));
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status_kp', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <flux:heading size="xl" level="1">Daftar Mahasiswa Bimbingan</flux:heading>
    <flux:subheading size="lg" class="mb-6">Daftar mahasiswa yang sedang Anda bimbing dalam Kerja Praktik.</flux:subheading>
    <flux:separator variant="subtle"/>

    {{-- Tabel Daftar Mahasiswa --}}
    <flux:card class="mt-8">
        {{-- Header Card dengan Search & Filter BARU --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
            <div class="flex-1 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau NIM mahasiswa..." icon="magnifying-glass" />
            </div>
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="statusFilter" placeholder="Filter Status KP">
                    <option value="">Semua Status KP</option>
                    <option value="Berlangsung">Berlangsung</option>
                    <option value="Selesai">Selesai</option>
                    <option value="Batal">Batal</option>
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->mahasiswaBimbingan">
            <flux:table.columns>
                <flux:table.column class="cursor-pointer" wire:click="sortBy('mahasiswa.nama_mahasiswa')">Nama Mahasiswa</flux:table.column>
                <flux:table.column>Judul Kerja Praktik</flux:table.column>
                <flux:table.column>Status KP</flux:table.column> {{-- KOLOM BARU --}}
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->mahasiswaBimbingan as $kp)
                    <flux:table.row :key="$kp->id">
                        <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }} ({{ $kp->mahasiswa->nim }})</flux:table.cell>
                        <flux:table.cell>{{ Str::limit($kp->judul_kp, 50) }}</flux:table.cell>
                        {{-- DATA BARU --}}
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
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button as="a" href="{{ route('dospem.bimbingan.detail', $kp->id) }}" size="xs" variant="primary">
                                Lihat Logbook
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">Tidak ada data ditemukan.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
