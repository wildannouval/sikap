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
use Livewire\WithPagination;

new #[Title('Pengajuan KP')] #[Layout('components.layouts.app')] class extends Component {
    // Gunakan trait WithFileUploads
    use WithFileUploads;
    use WithPagination;

    // Properti untuk form
    public string $judul_kp = '';
    public string $lokasi_kp = '';
    public $proposal_kp; // Properti untuk menampung file
    public $surat_keterangan_kp; // Properti untuk menampung file

    // Properti BARU untuk state
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    public ?KerjaPraktek $kpToView = null;

    /**
     * Mengambil riwayat pengajuan KP mahasiswa dengan filter dan search.
     */
    #[Computed]
    public function riwayatKp()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if (!$mahasiswaId) {
            return KerjaPraktek::where('id', -1)->paginate(5);
        }
        return KerjaPraktek::with('dosenPembimbing') // <-- TAMBAHKAN INI
        ->where('mahasiswa_id', $mahasiswaId)
            ->when($this->search, fn($q) => $q->where('judul_kp', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter, fn($q) => $q->where('status_pengajuan_kp', $this->statusFilter))
            ->latest()
            ->paginate(5);
    }

// Hook BARU untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter'])) {
            $this->resetPage();
        }
    }

    /**
     * Menyimpan data pengajuan KP.
     */
    public function save()
    {
        $validated = $this->validate([
            'judul_kp' => 'required|string|max:255',
            'lokasi_kp' => 'required|string|max:255',
            'proposal_kp' => 'required|file|mimes:pdf|max:2048',
            'surat_keterangan_kp' => 'required|file|mimes:pdf,jpg,png|max:2048',
        ]);
        $validated['proposal_kp'] = $this->proposal_kp->store('proposals', 'public');
        $validated['surat_keterangan_kp'] = $this->surat_keterangan_kp->store('surat-keterangan', 'public');
        $validated['mahasiswa_id'] = Auth::user()->mahasiswa->id;
        $validated['tanggal_pengajuan_kp'] = now();
        KerjaPraktek::create($validated);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan Kerja Praktik telah terkirim.');
        $this->reset('judul_kp', 'lokasi_kp', 'proposal_kp', 'surat_keterangan_kp');
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

    /**
     * Fungsi BARU untuk membuka modal detail.
     */
    public function showDetail($id)
    {
        $this->kpToView = KerjaPraktek::findOrFail($id);
        Flux::modal('detail-kp-modal')->show();
    }

    /**
     * Fungsi BARU untuk membatalkan upload proposal.
     */
    public function removeProposal()
    {
        $this->proposal_kp = null;
    }

    /**
     * Fungsi BARU untuk membatalkan upload surat keterangan.
     */
    public function removeSuratKeterangan()
    {
        $this->surat_keterangan_kp = null;
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
        <form wire:submit="save" enctype="multipart/form-data" class="space-y-6">
            {{-- PENYEMPURNAAN 2: Tampilan Upload File --}}
            <flux:input wire:model="judul_kp" label="Judul Kerja Praktik" placeholder="Judul atau topik KP Anda" required/>
            <flux:input wire:model="lokasi_kp" label="Lokasi Kerja Praktik" placeholder="Nama perusahaan/instansi" required/>
            {{-- Ganti <div> untuk upload proposal --}}
            <div>
                <flux:input wire:model="proposal_kp" type="file" label="Upload Proposal (PDF, maks 2MB)" required/>
                @if ($proposal_kp)
                    <div class="mt-1 flex items-center justify-between rounded-lg border bg-zinc-50 p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <span>File dipilih: {{ $proposal_kp->getClientOriginalName() }}</span>
                        <button type="button" wire:click="removeProposal" class="text-red-500 hover:text-red-700 font-bold text-lg">&times;</button>
                    </div>
                @endif
            </div>

            {{-- Ganti <div> untuk upload surat keterangan --}}
            <div>
                <flux:input wire:model="surat_keterangan_kp" type="file" label="Upload Surat Keterangan Diterima (PDF/JPG/PNG, maks 2MB)" required/>
                @if ($surat_keterangan_kp)
                    <div class="mt-1 flex items-center justify-between rounded-lg border bg-zinc-50 p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <span>File dipilih: {{ $surat_keterangan_kp->getClientOriginalName() }}</span>
                        <button type="button" wire:click="removeSuratKeterangan" class="text-red-500 hover:text-red-700 font-bold text-lg">&times;</button>
                    </div>
                @endif
            </div>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{-- Tampilkan spinner saat loading/upload --}}
                    <flux:icon name="loading" wire:loading wire:target="save" class="animate-spin"/>
                    <span wire:loading.remove wire:target="save">Kirim Pengajuan</span>
                    <span wire:loading wire:target="save">Mengirim...</span>
                </flux:button>
            </div>
        </form>
    </flux:card>

<flux:separator class="mt-4"/>
    {{-- Tabel Riwayat Pengajuan KP --}}
    <div class="mt-4">
        <flux:card class="mt-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <!-- Keterangan Kiri -->
                <div class="flex-1">
                    <flux:heading size="lg" class="text-lg">Riwayat Pengajuan KP Anda</flux:heading>
                    <flux:text variant="subtle" class="mt-1">
                        Data riwayat Pengajuan
                    </flux:text>
                </div>
                <!-- Filter, Search, dan Tombol -->
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <flux:select wire:model.live="statusFilter" size="sm" class="w-full sm:w-40"
                                 placeholder="Semua Status">
                        <option value="">Semua Status</option>
                        <option value="Diajukan">Diajukan</option>
                        <option value="Proses di Komisi">Proses di Komisi</option>
                        <option value="Disetujui">Disetujui</option>
                        <option value="Ditolak">Ditolak</option>
                        <option value="SPK Terbit">SPK Terbit</option>
                    </flux:select>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari judul KP..."
                        size="sm"
                        icon="magnifying-glass"
                        class="w-full sm:w-auto"
                    />
                </div>
            </div>
            {{-- PENYEMPURNAAN 1: Search dan Filter --}}
            <flux:separator variant="subtle" class="mb-6"/>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Judul KP</flux:table.column>
                    <flux:table.column>Tgl. Pengajuan</flux:table.column>
                    <flux:table.column>Tgl. Pengambilan SPK</flux:table.column> {{-- KOLOM BARU --}}
                    <flux:table.column>Dosen Pembimbing</flux:table.column>
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
                                {{-- Tampilkan nama jika sudah ada, jika tidak tampilkan strip --}}
                                {{ $kp->dosenPembimbing?->nama_dosen ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $color = match($kp->status_pengajuan_kp) {
                                        'Diajukan' => 'yellow',
                                        'Proses di Komisi' => 'blue',
                                        'Disetujui' => 'sky',
                                        'SPK Terbit' => 'green',
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
                                <div class="flex items-center gap-2">
                                    <flux:button size="xs" wire:click="showDetail({{ $kp->id }})">Detail</flux:button>
                                    @if ($kp->status_pengajuan_kp === 'Diajukan')
                                        <flux:modal.trigger :name="'delete-kp-' . $kp->id">
                                            <flux:button variant="danger" size="xs">Hapus</flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </div>
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
                            <flux:table.cell colspan="7" class="text-center">Anda belum pernah mengajukan KP.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            <div class="border-t p-4 dark:border-neutral-700">
                <Flux:pagination :poginator="$this->riwayatKp"/>
            </div>
        </flux:card>
    </div>

    {{-- Modal BARU untuk Lihat Detail --}}
    <flux:modal name="detail-kp-modal" class="md:w-[32rem]">
        @if ($kpToView)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Detail Pengajuan KP</flux:heading>
                    <flux:text class="mt-2">
                        Detail pengajuan KP Anda pada
                        tanggal {{ \Carbon\Carbon::parse($kpToView->tanggal_pengajuan_kp)->format('d F Y') }}.
                    </flux:text>
                </div>
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Judul KP</span>
                        <span class="col-span-2 font-semibold">{{ $kpToView->judul_kp }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Lokasi KP</span>
                        <span class="col-span-2">{{ $kpToView->lokasi_kp }}</span>
                    </div>
                    <hr class="dark:border-neutral-700">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Berkas</span>
                        <div class="col-span-2 flex items-center gap-2">
                            <flux:button as="a" href="{{ asset('storage/' . $kpToView->proposal_kp) }}" target="_blank"
                                         size="xs" icon="document-arrow-down">Unduh Proposal
                            </flux:button>
                            <flux:button as="a" href="{{ asset('storage/' . $kpToView->surat_keterangan_kp) }}"
                                         target="_blank" size="xs" icon="document-arrow-down">Unduh Surat Ket.
                            </flux:button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">Tutup</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
