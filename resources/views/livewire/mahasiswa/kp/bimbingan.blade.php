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
use App\Notifications\BimbinganBaru;
use Livewire\WithFileUploads;

new #[Title('Bimbingan KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

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

    public function mount()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if ($mahasiswaId) {
            $this->kerjaPraktek = KerjaPraktek::with('dosenPembimbing')
                ->where('mahasiswa_id', $mahasiswaId)
                ->where('status_pengajuan_kp', 'SPK Terbit')
                ->where('status_kp', 'Berlangsung')
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
            ->paginate(10);
    }

    public function updatedSearch() {
        $this->resetPage();
    }

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
        ],[
            'tanggal_konsultasi.required' => 'Tanggal bimbingan wajib diisi.',
            'topik_konsultasi.required' => 'Topik pembahasan wajib diisi.',
            'topik_konsultasi.min' => 'Topik pembahasan minimal harus 10 karakter.',
        ]);

        if ($this->editing) {
            $this->konsultasiToEdit->update($validated);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan berhasil diperbarui.');
        } else {
            $konsultasiBaru = Konsultasi::create([
                'kerja_praktek_id' => $this->kerjaPraktek->id,
                'mahasiswa_id' => $this->kerjaPraktek->mahasiswa_id,
                'dosen_pembimbing_id' => $this->kerjaPraktek->dosen_pembimbing_id,
                'tanggal_konsultasi' => $validated['tanggal_konsultasi'],
                'topik_konsultasi' => $validated['topik_konsultasi'],
            ]);

            $this->kerjaPraktek->dosenPembimbing->user->notify(new BimbinganBaru($konsultasiBaru));
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan berhasil disimpan.');
        }

        Flux::modal('bimbingan-modal')->close();
        $this->resetForm();
    }

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
    <div class="mb-6">
        <flux:heading size="xl" level="1">Bimbingan Kerja Praktik</flux:heading>
        <flux:subheading size="lg">Logbook dan catatan bimbingan Anda dengan dosen pembimbing.</flux:subheading>
    </div>

    @if ($kerjaPraktek)
        {{-- [START] PERUBAHAN LAYOUT MENJADI DUA KOLOM --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

            {{-- Kolom Kiri (Utama): Riwayat Bimbingan --}}
            <div class="lg:col-span-2">
                <flux:card>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
                        <div>
                            <flux:heading size="lg">Riwayat Bimbingan</flux:heading>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari topik..." icon="magnifying-glass" class="flex-1" />
                            <flux:button variant="primary" icon="plus" wire:click="openAddModal" class="whitespace-nowrap">Tambah Catatan</flux:button>
                        </div>
                    </div>
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
                                        @php
                                            $color = match($konsultasi->status_verifikasi) {
                                                'Menunggu Verifikasi' => 'yellow', 'Diverifikasi' => 'green', 'Revisi' => 'red', default => 'zinc',
                                            };
                                        @endphp
                                        <flux:badge :color="$color" size="sm">{{ $konsultasi->status_verifikasi }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($konsultasi->status_verifikasi === 'Revisi' && $konsultasi->catatan_konsultasi)
                                            <flux:dropdown position="top" align="start">
                                                <flux:button variant="ghost" size="xs" class="!text-indigo-600 !p-0">Lihat Catatan</flux:button>
                                                <flux:popover class="max-w-xs p-3 text-sm">{{ $konsultasi->catatan_konsultasi }}</flux:popover>
                                            </flux:dropdown>
                                        @else - @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($konsultasi->status_verifikasi === 'Menunggu Verifikasi')
                                            <div class="flex items-center gap-2">
                                                <flux:button size="xs" wire:click="openEditModal({{ $konsultasi->id }})">Edit</flux:button>
                                                <flux:modal.trigger :name="'delete-bimbingan-' . $konsultasi->id">
                                                    <flux:button variant="danger" size="xs">Hapus</flux:button>
                                                </flux:modal.trigger>
                                            </div>
                                        @else - @endif
                                    </flux:table.cell>
                                </flux:table.row>

                                @if ($konsultasi->status_verifikasi === 'Menunggu Verifikasi')
                                    <flux:modal :name="'delete-bimbingan-' . $konsultasi->id" class="md:w-96">
                                        <div class="space-y-6 text-center">
                                            <flux:icon name="trash" class="mx-auto size-10 text-danger" />
                                            <div>
                                                <flux:heading size="lg">Hapus Catatan Bimbingan?</flux:heading>
                                                <flux:text class="mt-2">Anda yakin ingin menghapus catatan bimbingan tanggal {{ \Carbon\Carbon::parse($konsultasi->tanggal_konsultasi)->format('d F Y') }}?</flux:text>
                                            </div>
                                            <div class="flex justify-center gap-3">
                                                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                                <flux:button variant="danger" wire:click="delete({{ $konsultasi->id }})">Ya, Hapus</flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @endif
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5">
                                        <div class="text-center py-12">
                                            <flux:icon name="document-plus" class="size-10 mx-auto text-zinc-400"/>
                                            <h3 class="mt-2 text-md font-medium text-zinc-800 dark:text-zinc-200">Belum Ada Catatan</h3>
                                            <p class="mt-1 text-sm text-zinc-500">Klik "Tambah Catatan" untuk mencatat bimbingan pertama Anda.</p>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </div>

            {{-- Kolom Kanan (Informasi) --}}
            <div class="lg:col-span-1 space-y-8">
                <flux:card>
                    <div class="space-y-4">
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

                <flux:card>
                    <h3 class="text-lg font-semibold mb-4">Makna Status Verifikasi</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <flux:badge color="yellow" class="mt-0.5">Menunggu Verifikasi</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Catatan terkirim dan menunggu persetujuan dosen. Masih bisa di-edit atau dihapus.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:badge color="green" class="mt-0.5">Diverifikasi</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Catatan bimbingan telah disetujui oleh dosen dan tidak dapat diubah lagi.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:badge color="red" class="mt-0.5">Revisi</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Dosen meminta perbaikan. Cek "Catatan Dosen" pada tabel dan edit kembali entri Anda.</p>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
        {{-- [END] PERUBAHAN LAYOUT --}}
        
    @else
        {{-- Tampilan jika tidak ada KP aktif --}}
        <flux:separator variant="subtle" class="my-6"/>
        <flux:card class="mt-8 text-center">
            <flux:icon name="information-circle" class="mx-auto size-12 text-zinc-400" />
            <p class="mt-4 font-semibold">Kerja Praktik Belum Aktif</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Anda belum bisa mengisi logbook bimbingan karena SPK belum terbit atau KP belum berstatus "Berlangsung".</p>
            <flux:button as="a" href="{{ route('kp.pengajuan') }}" variant="primary" size="sm" class="mt-4">
                Lihat Status Pengajuan KP
            </flux:button>
        </flux:card>
    @endif

    {{-- Modal untuk Tambah/Edit Catatan Bimbingan --}}
    <flux:modal name="bimbingan-modal" class="md:w-[32rem]">
        <form wire:submit="save">
            <div class="space-y-6">
                <div><flux:heading size="lg">{{ $editing ? 'Edit' : 'Tambah' }} Catatan Bimbingan</flux:heading></div>
                <div class="space-y-4">
                    <flux:input wire:model="tanggal_konsultasi" type="date" label="Tanggal Bimbingan" required />
                    <flux:textarea wire:model="topik_konsultasi" label="Topik / Pembahasan Bimbingan" placeholder="Jelaskan apa saja yang Anda diskusikan..." rows="5" required />
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan Catatan</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>