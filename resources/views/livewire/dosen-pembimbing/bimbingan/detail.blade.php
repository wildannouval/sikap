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
use App\Notifications\BimbinganDiverifikasi;
use App\Notifications\BimbinganRevisi;

new #[Title('Detail Bimbingan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public KerjaPraktek $kp;

    // Properti untuk Modal Verifikasi
    public ?Konsultasi $konsultasiToProcess = null;
    public string $catatan_dosen = '';

    // Properti BARU untuk Search, Filter, Sort
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'tanggal_konsultasi';
    #[Url]
    public string $sortDirection = 'desc';

    /**
     * Menerima data KerjaPraktek dari route.
     */
    public function mount(KerjaPraktek $kp)
    {
        if ($kp->dosen_pembimbing_id !== Auth::user()->dosen?->id) {
            abort(403, 'Akses Ditolak');
        }
        $this->kp = $kp;
    }

    #[Computed]
    public function konsultasis()
    {
        return Konsultasi::where('kerja_praktek_id', $this->kp->id)
            ->when($this->search, fn($q) => $q->where('topik_konsultasi', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter, fn($q) => $q->where('status_verifikasi', $this->statusFilter))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }

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

    public function openVerificationModal($id)
    {
        $this->konsultasiToProcess = Konsultasi::findOrFail($id);
        $this->catatan_dosen = $this->konsultasiToProcess->catatan_konsultasi ?? '';
        Flux::modal('verification-modal')->show();
    }

    public function verify(string $newStatus)
    {
        if (!in_array($newStatus, ['Diverifikasi', 'Revisi'])) { return; }
        if ($newStatus === 'Revisi') {
            $this->validate(['catatan_dosen' => 'required|string|min:5'],[
                'catatan_dosen.required' => 'Catatan wajib diisi.'
            ]);
        }
        if ($this->konsultasiToProcess) {
            $this->konsultasiToProcess->update([
                'status_verifikasi' => $newStatus,
                'catatan_konsultasi' => $this->catatan_dosen,
                'tanggal_verifikasi' => now(),
            ]);
            Flux::modal('verification-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan telah diverifikasi.');
        }
        if ($newStatus === 'Diverifikasi') {
            $this->konsultasiToProcess->mahasiswa->user->notify(new BimbinganDiverifikasi($this->konsultasiToProcess));
        } else { // Revisi
            $this->konsultasiToProcess->mahasiswa->user->notify(new BimbinganRevisi($this->konsultasiToProcess));
        }
    }

    /**
     * Fungsi BARU untuk membatalkan verifikasi.
     */
    public function cancelVerification($id)
    {
        $konsultasi = Konsultasi::findOrFail($id);

        // Otorisasi sederhana
        if ($konsultasi->dosen_pembimbing_id !== Auth::user()->dosen?->id) {
            return;
        }

        // Kembalikan status ke awal
        $konsultasi->update([
            'status_verifikasi' => 'Menunggu Verifikasi',
            'catatan_konsultasi' => null,
            'tanggal_verifikasi' => null,
        ]);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Verifikasi telah dibatalkan.');
    }
}; ?>

<div>
    {{-- Tombol Kembali --}}
    <flux:button as="a" href="{{ route('dospem.mahasiswa') }}" variant="ghost" icon="arrow-left" class="mb-4">
        Kembali ke Daftar Mahasiswa
    </flux:button>

    {{-- Header Halaman --}}
    <flux:heading size="xl" level="1">Detail Bimbingan KP</flux:heading>
    <flux:subheading size="lg" class="mb-6">Logbook bimbingan untuk mahasiswa: <span class="font-bold">{{ $kp->mahasiswa->nama_mahasiswa }}</span></flux:subheading>
    <flux:separator variant="subtle"/>

    {{-- Tabel Riwayat Bimbingan --}}
    <flux:card class="mt-8">
        {{-- Header Card dengan tombol Search & Filter BARU --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
            <div class="flex-1 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari topik bimbingan..." icon="magnifying-glass" />
            </div>
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="statusFilter" placeholder="Filter Status">
                    <option value="">Semua Status</option>
                    <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                    <option value="Diverifikasi">Diverifikasi</option>
                    <option value="Revisi">Revisi</option>
                </flux:select>
            </div>
        </div>
        <flux:table :paginate="$this->konsultasis">
            <flux:table.columns>
                <flux:table.column class="cursor-pointer" wire:click="sortBy('tanggal_konsultasi')">Tanggal</flux:table.column>
                <flux:table.column>Topik Pembahasan</flux:table.column>
                <flux:table.column>Status Verifikasi</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->konsultasis as $konsultasi)
                    <flux:table.row :key="$konsultasi->id">
                        <flux:table.cell>{{ \Carbon\Carbon::parse($konsultasi->tanggal_konsultasi)->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $konsultasi->topik_konsultasi }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match($konsultasi->status_verifikasi) {
                                    'Menunggu Verifikasi' => 'yellow',
                                    'Diverifikasi' => 'green',
                                    'Revisi' => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$color" size="sm">{{ $konsultasi->status_verifikasi }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                {{-- Tombol ini sekarang selalu ada --}}
                                <flux:button size="xs" variant="primary" wire:click="openVerificationModal({{ $konsultasi->id }})">
                                    {{ $konsultasi->status_verifikasi === 'Menunggu Verifikasi' ? 'Verifikasi' : 'Edit' }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                    {{-- Modal Konfirmasi untuk Batalkan Verifikasi --}}
                    @if($konsultasi->status_verifikasi !== 'Menunggu Verifikasi')
                        <flux:modal :name="'cancel-verification-' . $konsultasi->id" class="md:w-96">
                            <div class="space-y-6 text-center">
                                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-yellow-100">
                                    <flux:icon name="arrow-uturn-left" class="size-6 text-yellow-600" />
                                </div>
                                <div>
                                    <flux:heading size="lg">Batalkan Verifikasi?</flux:heading>
                                    <flux:text class="mt-2">
                                        Anda yakin ingin membatalkan verifikasi ini? Status akan kembali menjadi 'Menunggu Verifikasi'.
                                    </flux:text>
                                </div>
                                <div class="flex justify-center gap-3">
                                    <flux:modal.close><flux:button variant="ghost">Tidak</flux:button></flux:modal.close>
                                    <flux:button variant="primary" color="yellow" wire:click="cancelVerification({{ $konsultasi->id }})">Ya, Batalkan</flux:button>
                                </div>
                            </div>
                        </flux:modal>
                    @endif
                @empty
                    <flux:table.row><flux:table.cell colspan="4" class="text-center">Belum ada catatan bimbingan dari mahasiswa ini.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal untuk Verifikasi --}}
    <flux:modal name="verification-modal" class="md:w-[32rem]">
        @if ($konsultasiToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Verifikasi Catatan Bimbingan</flux:heading>
                </div>
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm">{{ $konsultasiToProcess->topik_konsultasi }}</p>
                </div>
                <div>
                    <flux:textarea wire:model="catatan_dosen" label="Catatan / Feedback Anda (Wajib diisi jika revisi)" />
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Tutup</flux:button></flux:modal.close>
                    <flux:button wire:click="verify('Revisi')" variant="danger">Tolak (Revisi)</flux:button>
                    <flux:button wire:click="verify('Diverifikasi')" variant="primary">Verifikasi</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
