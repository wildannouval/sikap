<?php

use App\Models\KerjaPraktek;
use App\Models\Konsultasi;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\WithPagination;

new #[Title('Bimbingan KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    public ?KerjaPraktek $kerjaPraktek = null;
    public bool $editing = false;
    public ?Konsultasi $konsultasiToEdit = null;
    public string $tanggal_konsultasi = '';
    public string $topik_konsultasi = '';
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $sortField = 'tanggal_konsultasi';
    #[Url]
    public string $sortDirection = 'desc';

    // Hook untuk reset paginasi
    public function updatedSearch() {
        $this->resetPage();
    }

    public function mount()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if ($mahasiswaId) {
            $this->kerjaPraktek = KerjaPraktek::with('dosenPembimbing')
                ->where('mahasiswa_id', $mahasiswaId)
                ->where('status_pengajuan_kp', 'SPK Terbit')
                ->first();
        }
    }

    #[Computed]
    public function konsultasis()
    {
        if (!$this->kerjaPraktek) {
            return Konsultasi::where('id', -1)->paginate(5);
        }
        return Konsultasi::where('kerja_praktek_id', $this->kerjaPraktek->id)
            ->when($this->search, fn($q) => $q->where('topik_konsultasi', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(5);
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

    public function openAddModal()
    {
        $this->resetForm();
        Flux::modal('bimbingan-modal')->show();
    }

    public function openEditModal($id)
    {
        $this->resetForm();
        $this->editing = true;
        $this->konsultasiToEdit = Konsultasi::findOrFail($id);
        $this->tanggal_konsultasi = $this->konsultasiToEdit->tanggal_konsultasi;
        $this->topik_konsultasi = $this->konsultasiToEdit->topik_konsultasi;
        Flux::modal('bimbingan-modal')->show();
    }

    public function save()
    {
        $validated = $this->validate([
            'tanggal_konsultasi' => 'required|date',
            'topik_konsultasi' => 'required|string|min:10',
        ]);

        if ($this->editing) {
            $this->konsultasiToEdit->update($validated);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan berhasil diperbarui.');
        } else {
            Konsultasi::create([
                'kerja_praktek_id' => $this->kerjaPraktek->id,
                'mahasiswa_id' => $this->kerjaPraktek->mahasiswa_id,
                'dosen_pembimbing_id' => $this->kerjaPraktek->dosen_pembimbing_id,
                'tanggal_konsultasi' => $validated['tanggal_konsultasi'],
                'topik_konsultasi' => $validated['topik_konsultasi'],
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan berhasil disimpan.');
        }

        Flux::modal('bimbingan-modal')->close();
        $this->resetForm();
    }

    /**
     * Menghapus catatan bimbingan yang belum diverifikasi.
     */
    public function delete($id)
    {
        $konsultasi = Konsultasi::findOrFail($id);
        if ($konsultasi->mahasiswa_id !== Auth::user()->mahasiswa?->id || $konsultasi->status_verifikasi !== 'Menunggu Verifikasi') {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Catatan ini tidak dapat dihapus.');
            return;
        }

        $konsultasi->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan berhasil dihapus.');
    }

    private function resetForm()
    {
        $this->reset(['editing', 'konsultasiToEdit', 'tanggal_konsultasi', 'topik_konsultasi']);
        $this->tanggal_konsultasi = now()->format('Y-m-d');
        $this->resetErrorBag();
    }
}; ?>

<div>
    <flux:heading size="xl" level="1">Bimbingan Kerja Praktik</flux:heading>

    @if ($kerjaPraktek)
        <flux:subheading size="lg" class="mb-6">Logbook dan catatan bimbingan Anda dengan dosen pembimbing.</flux:subheading>
        <flux:separator variant="subtle"/>

        {{-- Informasi KP Aktif --}}
        <flux:card class="mt-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <flux:label>Judul Kerja Praktik</flux:label>
                    <p class="font-semibold">{{ $kerjaPraktek->judul_kp }}</p>
                </div>
                <div>
                    <flux:label>Lokasi</flux:label>
                    <p class="font-semibold">{{ $kerjaPraktek->lokasi_kp }}</p>
                </div>
                <div>
                    <flux:label>Dosen Pembimbing</flux:label>
                    <p class="font-semibold">{{ $kerjaPraktek->dosenPembimbing->nama_dosen ?? 'Belum Ditugaskan' }}</p>
                </div>
            </div>
        </flux:card>

        {{-- Form Tambah Catatan Bimbingan --}}
        <div class="mt-8">
{{--            <div class="flex items-center justify-between">--}}
{{--                <flux:heading size="lg">Riwayat Bimbingan</flux:heading>--}}
{{--                <flux:button variant="primary" size="sm" icon="plus" wire:click="openAddModal">Tambah Catatan</flux:button>--}}
{{--            </div>--}}
            <flux:card>
                {{-- Header Card dengan tombol Tambah --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
                    <div>
                        <flux:heading size="lg">Riwayat Bimbingan</flux:heading>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari topik..." icon="magnifying-glass" class="flex-1" />
                        <flux:button variant="primary" icon="plus" wire:click="openAddModal" class="whitespace-nowrap">Tambah Catatan</flux:button>
                    </div>
                </div>
                {{-- Search & Filter --}}
{{--                <div class="mb-4">--}}
{{--                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari topik bimbingan..." icon="magnifying-glass" />--}}
{{--                </div>--}}
                <flux:table :paginate="$this->konsultasis">
                    <flux:table.columns>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('tanggal_konsultasi')">Tanggal</flux:table.column>
                        <flux:table.column>Topik Pembahasan</flux:table.column>
                        <flux:table.column>Status Verifikasi</flux:table.column>
                        <flux:table.column>Catatan Dosen</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->konsultasis as $konsultasi)
                            <flux:table.row :key="$konsultasi->id">
                                <flux:table.cell>{{ \Carbon\Carbon::parse($konsultasi->tanggal_konsultasi)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($konsultasi->topik_konsultasi, 50) }}</flux:table.cell>
                                <flux:table.cell>
                                    {{-- ... badge status tidak berubah ... --}}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{-- PENYEMPURNAAN 2: Popover Catatan Revisi --}}
                                    @if ($konsultasi->status_verifikasi === 'Revisi' && $konsultasi->catatan_konsultasi)
                                        <flux:dropdown position="top" align="start">
                                            <flux:button variant="ghost" size="xs" class="!text-indigo-600 !p-0">Lihat Catatan</flux:button>
                                            <flux:popover class="max-w-xs p-3 text-sm">{{ $konsultasi->catatan_konsultasi }}</flux:popover>
                                        </flux:dropdown>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($konsultasi->status_verifikasi === 'Menunggu Verifikasi')
                                        <div class="flex items-center gap-2">
                                            {{-- PENYEMPURNAAN 1: Tombol Edit --}}
                                            <flux:button size="xs" wire:click="openEditModal({{ $konsultasi->id }})">Edit</flux:button>
                                            <flux:button variant="danger" size="xs" wire:click="delete({{ $konsultasi->id }})" wire:confirm="Yakin ingin menghapus catatan bimbingan ini?">Hapus</flux:button>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5" class="text-center">Belum ada catatan bimbingan.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @else
        {{-- Tampilan jika tidak ada KP aktif --}}
        <flux:card class="mt-8 text-center">
            <flux:icon name="information-circle" class="mx-auto size-12 text-zinc-400" />
            <p class="mt-4 font-semibold">Kerja Praktik Belum Aktif</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Anda belum bisa mengisi logbook karena pengajuan KP Anda belum disetujui atau dosen pembimbing belum ditugaskan.</p>
        </flux:card>
    @endif

    {{-- Modal untuk Tambah/Edit Catatan Bimbingan --}}
    <flux:modal name="bimbingan-modal" class="md:w-[32rem]">
        <div class="space-y-6">
            <div><flux:heading size="lg">{{ $editing ? 'Edit' : 'Tambah' }} Catatan Bimbingan</flux:heading></div>
            <div class="space-y-4">
                <flux:input wire:model="tanggal_konsultasi" type="date" label="Tanggal Bimbingan" required />
                <flux:textarea wire:model="topik_konsultasi" label="Topik / Pembahasan Bimbingan" placeholder="Jelaskan apa saja yang Anda diskusikan..." required />
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button wire:click="save" variant="primary">Simpan Catatan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
