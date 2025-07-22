<?php

use App\Models\KerjaPraktek;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Title('Pengajuan KP')] #[Layout('components.layouts.app')] class extends Component {
    // Gunakan trait WithFileUploads
    use WithFileUploads;

    // Properti untuk form
    public string $judul_kp = '';
    public string $lokasi_kp = '';
    public $proposal_kp; // Properti untuk menampung file
    public $surat_keterangan_kp; // Properti untuk menampung file

    /**
     * Mengambil riwayat pengajuan KP mahasiswa.
     */
    #[Computed]
    public function riwayatKp()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if (!$mahasiswaId) {
            return collect();
        }
        return KerjaPraktek::where('mahasiswa_id', $mahasiswaId)->latest()->get();
    }

    /**
     * Menyimpan data pengajuan KP.
     */
    public function save()
    {
        // Validasi, termasuk validasi file
        $validated = $this->validate([
            'judul_kp' => 'required|string|max:255',
            'lokasi_kp' => 'required|string|max:255',
            'proposal_kp' => 'required|file|mimes:pdf|max:2048', // Wajib, PDF, maks 2MB
            'surat_keterangan_kp' => 'required|file|mimes:pdf,jpg,png|max:2048', // Wajib, PDF/Gambar, maks 2MB
        ]);

        // Simpan file ke storage dan dapatkan path-nya
        $validated['proposal_kp'] = $this->proposal_kp->store('proposals', 'public');
        $validated['surat_keterangan_kp'] = $this->surat_keterangan_kp->store('surat-keterangan', 'public');

        // Tambahkan data mahasiswa dan tanggal
        $validated['mahasiswa_id'] = Auth::user()->mahasiswa->id;
        $validated['tanggal_pengajuan_kp'] = now();

        // Simpan ke database
        KerjaPraktek::create($validated);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan Kerja Praktik telah terkirim.');
        $this->reset();
    }

    /**
     * Fungsi baru untuk menghapus pengajuan KP.
     */
    public function delete($id)
    {
        $kp = KerjaPraktek::findOrFail($id);

        // Otorisasi: pastikan mahasiswa hanya bisa menghapus miliknya sendiri
        if (Auth::user()->mahasiswa->id !== $kp->mahasiswa_id) {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Anda tidak berhak melakukan aksi ini.');
            return;
        }

        // Hanya izinkan hapus jika status masih 'Diajukan'
        if ($kp->status_pengajuan_kp !== 'Diajukan') {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Pengajuan yang sudah diproses tidak dapat dihapus.');
            return;
        }

        // Hapus file dari storage sebelum menghapus record database
        Storage::disk('public')->delete($kp->proposal_kp);
        Storage::disk('public')->delete($kp->surat_keterangan_kp);

        $kp->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP berhasil dihapus.');
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Pengajuan Kerja Praktik</flux:heading>
            <flux:subheading size="lg" class="mb-6">Lengkapi form di bawah ini untuk mengajukan KP secara resmi.
            </flux:subheading>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    {{-- Form Pengajuan --}}
    <flux:card class="mt-8 w-full">
        {{-- Penting: tambahkan enctype untuk form dengan file upload --}}
        <form wire:submit="save" enctype="multipart/form-data">
            <div class="space-y-6">
                <flux:input wire:model="judul_kp" label="Judul Kerja Praktik" placeholder="Judul atau topik KP Anda"
                            required/>
                <flux:input wire:model="lokasi_kp" label="Lokasi Kerja Praktik" placeholder="Nama perusahaan/instansi"
                            required/>
                <flux:input wire:model="proposal_kp" type="file" label="Upload Proposal (PDF, maks 2MB)" required/>
                <flux:input wire:model="surat_keterangan_kp" type="file"
                            label="Upload Surat Keterangan Diterima (PDF/JPG/PNG, maks 2MB)" required/>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        {{-- Tampilkan spinner saat loading/upload --}}
                        <flux:icon name="loading" wire:loading wire:target="save" class="animate-spin"/>
                        <span wire:loading.remove wire:target="save">Kirim Pengajuan</span>
                        <span wire:loading wire:target="save">Mengirim...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:card>

    {{-- Tabel Riwayat Pengajuan KP --}}
    <div class="mt-8">
        <flux:heading size="lg">Riwayat Pengajuan KP Anda</flux:heading>
        <flux:card class="mt-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Judul KP</flux:table.column>
                    <flux:table.column>Tgl. Pengajuan</flux:table.column>
                    <flux:table.column>Tgl. Pengambilan SPK</flux:table.column> {{-- KOLOM BARU --}}
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Keterangan</flux:table.column> {{-- KOLOM BARU --}}
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->riwayatKp as $kp)
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ Str::limit($kp->judul_kp, 30) }}</flux:table.cell>
                            <flux:table.cell>{{ \Carbon\Carbon::parse($kp->tanggal_pengajuan_kp)->format('d/m/Y') }}</flux:table.cell>
                            {{-- DATA BARU --}}
                            <flux:table.cell>{{ $kp->tanggal_pengambilan_spk ? \Carbon\Carbon::parse($kp->tanggal_pengambilan_spk)->format('d/m/Y') : '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $color = match($kp->status_pengajuan_kp) {
                                        'Diajukan' => 'yellow',
                                        'Disetujui' => 'green',
                                        'Ditolak' => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$color" size="sm">{{ $kp->status_pengajuan_kp }}</flux:badge>
                            </flux:table.cell>
                            {{-- DATA BARU --}}
                            <flux:table.cell>
                                @if ($kp->status_pengajuan_kp === 'Ditolak' && $kp->catatan_kp)
                                    <flux:dropdown position="top" align="start">
                                        <flux:button variant="ghost" size="xs" class="!text-indigo-600 !p-0">
                                            Lihat Catatan
                                        </flux:button>
                                        <flux:popover class="max-w-xs p-3 text-sm">
                                            {{ $kp->catatan_kp }}
                                        </flux:popover>
                                    </flux:dropdown>
                                @else
                                    -
                                @endif
                            </flux:table.cell>
                            {{-- DATA BARU --}}
                            <flux:table.cell>
                                @if ($kp->status_pengajuan_kp === 'Diajukan')
                                    <flux:modal.trigger :name="'delete-kp-' . $kp->id">
                                        <flux:button variant="danger" size="xs">Hapus</flux:button>
                                    </flux:modal.trigger>
                                @else
                                    -
                                @endif
                            </flux:table.cell>
                        </flux:table.row>

                        {{-- Modal Konfirmasi Hapus --}}
                        @if ($kp->status_pengajuan_kp === 'Diajukan')
                            <flux:modal :name="'delete-kp-' . $kp->id" class="md:w-96">
                                <div class="space-y-6 text-center">
                                    <div
                                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                        <flux:icon name="trash" class="size-6 text-red-600"/>
                                    </div>
                                    <div>
                                        <flux:heading size="lg">Hapus Pengajuan KP?</flux:heading>
                                        <flux:text class="mt-2">
                                            Anda yakin ingin menghapus pengajuan KP dengan judul <span
                                                class="font-bold">"{{ Str::limit($kp->judul_kp, 30) }}"</span>?
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">Batal</flux:button>
                                        </flux:modal.close>
                                        <flux:button variant="danger" wire:click="delete({{ $kp->id }})">Ya, Hapus
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        @endif
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center">Anda belum pernah mengajukan KP.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</div>
