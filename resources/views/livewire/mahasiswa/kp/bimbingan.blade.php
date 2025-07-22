<?php

use App\Models\KerjaPraktek;
use App\Models\Konsultasi;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Title('Bimbingan KP')] #[Layout('components.layouts.app')] class extends Component {
    // Properti untuk menampung data KP aktif mahasiswa
    public ?KerjaPraktek $kerjaPraktek = null;

    // Properti untuk form catatan bimbingan
    public string $tanggal_konsultasi = '';
    public string $topik_konsultasi = '';

    /**
     * Dijalankan saat komponen dimuat, untuk mencari KP aktif mahasiswa.
     */
    public function mount()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if ($mahasiswaId) {
            // Cari KP yang statusnya sudah SPK Terbit dan sedang berlangsung
            $this->kerjaPraktek = KerjaPraktek::with('dosenPembimbing')
                ->where('mahasiswa_id', $mahasiswaId)
                ->where('status_pengajuan_kp', 'SPK Terbit')
                // ->where('status_kp', 'Berlangsung') // bisa diaktifkan nanti
                ->first();
        }

        // Set tanggal default ke hari ini
        $this->tanggal_konsultasi = now()->format('Y-m-d');
    }

    /**
     * Mengambil riwayat konsultasi untuk KP yang aktif.
     */
    #[Computed]
    public function konsultasis()
    {
        if (!$this->kerjaPraktek) {
            return collect();
        }
        return Konsultasi::where('kerja_praktek_id', $this->kerjaPraktek->id)
            ->latest('tanggal_konsultasi')
            ->get();
    }

    /**
     * Menyimpan catatan bimbingan baru.
     */
    public function save()
    {
        $validated = $this->validate([
            'tanggal_konsultasi' => 'required|date',
            'topik_konsultasi' => 'required|string|min:10',
        ]);

        Konsultasi::create([
            'kerja_praktek_id' => $this->kerjaPraktek->id,
            'mahasiswa_id' => $this->kerjaPraktek->mahasiswa_id,
            'dosen_pembimbing_id' => $this->kerjaPraktek->dosen_pembimbing_id,
            'tanggal_konsultasi' => $validated['tanggal_konsultasi'],
            'topik_konsultasi' => $validated['topik_konsultasi'],
        ]);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Catatan bimbingan berhasil disimpan.');
        $this->reset('topik_konsultasi');
        $this->tanggal_konsultasi = now()->format('Y-m-d');
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
        <flux:card class="mt-8">
            <h2 class="text-lg font-bold">Tambah Catatan Bimbingan Baru</h2>
            <form wire:submit="save" class="mt-4 space-y-4">
                <flux:input wire:model="tanggal_konsultasi" type="date" label="Tanggal Bimbingan" required />
                <flux:textarea wire:model="topik_konsultasi" label="Topik / Pembahasan Bimbingan" placeholder="Jelaskan apa saja yang Anda diskusikan dengan pembimbing..." required />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">Simpan Catatan</flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Tabel Riwayat Bimbingan --}}
        <div class="mt-8">
            <flux:heading size="lg">Riwayat Bimbingan</flux:heading>
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Tanggal</flux:table.column>
                        <flux:table.column>Topik Pembahasan</flux:table.column>
                        <flux:table.column>Status Verifikasi</flux:table.column>
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
                                            'Menunggu Verifikasi' => 'yellow',
                                            'Diverifikasi' => 'green',
                                            'Revisi' => 'red',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge :color="$color" size="sm">{{ $konsultasi->status_verifikasi }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($konsultasi->status_verifikasi === 'Menunggu Verifikasi')
                                        <flux:button variant="danger" size="xs" wire:click="delete({{ $konsultasi->id }})" wire:confirm="Yakin ingin menghapus catatan bimbingan ini?">Hapus</flux:button>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center">Belum ada catatan bimbingan.</flux:table.cell>
                            </flux:table.row>
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
</div>
