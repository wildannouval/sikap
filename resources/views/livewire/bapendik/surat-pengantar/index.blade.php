<?php

use App\Models\SuratPengantar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Validasi Surat Pengantar')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter'])) {
            $this->resetPage();
        }
    }

    // Properti untuk Modal Penolakan
    public ?int $suratToRejectId = null;
    public string $rejectionNote = '';

    // Properti baru untuk Modal Persetujuan
    public ?SuratPengantar $suratToProcess = null;
    public string $tanggalPengambilan = '';


    #[Computed]
    public function pengajuanSurat()
    {
        return SuratPengantar::with('mahasiswa.user')
            // Logika Search
            ->when($this->search, function ($query) {
                $query->whereHas('mahasiswa', function ($q) {
                    $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                        ->orWhere('nim', 'like', '%' . $this->search . '%');
                })->orWhere('lokasi_surat_pengantar', 'like', '%' . $this->search . '%');
            })
            // Logika Filter Status
            ->when($this->statusFilter, function ($query) {
                $query->where('status_surat_pengantar', $this->statusFilter);
            })
            ->latest()
            ->paginate(10);
    }

    /**
     * Membuka modal persetujuan dan mengisi data surat yang akan diproses.
     */
    public function openApproveModal($id)
    {
        $this->suratToProcess = SuratPengantar::findOrFail($id);
        $this->reset('tanggalPengambilan'); // Kosongkan tanggal setiap membuka modal
        Flux::modal('approve-modal')->show();
    }

    /**
     * Aksi untuk menyetujui surat dari dalam modal.
     */
    public function approve()
    {
        $this->validate([
            'tanggalPengambilan' => 'required|date',
        ]);

        if ($this->suratToProcess) {
            $this->suratToProcess->update([
                'status_surat_pengantar' => 'Disetujui',
                'tanggal_disetujui_surat_pengantar' => now(),
                'tanggal_pengambilan_surat_pengantar' => $this->tanggalPengambilan,
            ]);

            Flux::modal('approve-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat pengantar berhasil disetujui.');
            $this->reset('suratToProcess', 'tanggalPengambilan');
        }
    }

    /**
     * Membuka modal penolakan.
     */
    public function openRejectModal($id)
    {
        $this->suratToRejectId = $id;
        $this->reset('rejectionNote');
        Flux::modal('reject-modal')->show();
    }

    /**
     * Aksi untuk menolak surat.
     */
    public function reject()
    {
        $validated = $this->validate([
            'rejectionNote' => 'required|string',
        ]);

        $surat = SuratPengantar::findOrFail($this->suratToRejectId);
        $surat->update([
            'status_surat_pengantar' => 'Ditolak',
            'catatan_surat' => $validated['rejectionNote'],
            'tanggal_disetujui_surat_pengantar' => null, // Pastikan tanggal lain di-reset
            'tanggal_pengambilan_surat_pengantar' => null,
        ]);

        Flux::modal('reject-modal')->close();
        Flux::modal('approve-modal')->close();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat pengantar telah ditolak.');
        $this->reset('suratToRejectId', 'rejectionNote');
    }

    /**
     * Fungsi BARU untuk membatalkan status (Disetujui/Ditolak) kembali ke 'Diajukan'.
     */
    public function cancelStatus($id)
    {
        $surat = SuratPengantar::findOrFail($id);

        // Kembalikan status ke 'Diajukan' dan hapus data status sebelumnya
        $surat->update([
            'status_surat_pengantar' => 'Diajukan',
            'catatan_surat' => null,
            'tanggal_disetujui_surat_pengantar' => null,
            'tanggal_pengambilan_surat_pengantar' => null,
        ]);

        Flux::modal('approve-modal')->close();
        Flux::modal('cancel-approve-modal-' . $id)->close();
        Flux::modal('cancel-reject-modal-' . $id)->close();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Status pengajuan telah dibatalkan.');
    }

}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Validasi Surat Pengantar</flux:heading>
            <flux:subheading size="lg" class="mb-6">Kelola dan validasi semua pengajuan surat dari mahasiswa.
            </flux:subheading>
        </div>
    </div>
    <flux:separator/>

    {{-- Tabel dalam Card --}}
    <flux:card class="space-y-6 mt-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <!-- Keterangan Kiri -->
            <div class="flex-1">
                <flux:heading size="lg" class="text-lg">Daftar Pengajuan Surat Pengantar</flux:heading>
                <flux:text variant="subtle" class="mt-1">
                    Dibawah ini data daftar pengajuan mahasiswa yang perlu di proses.
                </flux:text>
            </div>

            <!-- Filter, Search, dan Tombol -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                <flux:select wire:model.live="statusFilter" size="sm" class="w-full sm:w-40" placeholder="Semua Status">
                    <option value="">Semua Status</option>
                    <option value="Diajukan">Diajukan</option>
                    <option value="Disetujui">Disetujui</option>
                    <option value="Ditolak">Ditolak</option>
                </flux:select>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari mhs/nim/instansi..."
                    size="sm"
                    icon="magnifying-glass"
                    class="w-full sm:w-auto"
                />

                <flux:modal.trigger name="create-pengajuan-surat-pengantar">
                    <flux:button variant="primary" icon="plus" size="sm">Buat Pengajuan</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <flux:separator variant="subtle"/>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama Mahasiswa</flux:table.column>
                <flux:table.column>NIM</flux:table.column>
                <flux:table.column>Instansi Tujuan</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->pengajuanSurat as $surat)
                    <flux:table.row :key="$surat->id">
                        <flux:table.cell variant="strong">{{ $surat->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                        <flux:table.cell>{{ $surat->mahasiswa->nim }}</flux:table.cell>
                        <flux:table.cell>{{ $surat->lokasi_surat_pengantar }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match($surat->status_surat_pengantar) {
                                    'Diajukan' => 'yellow',
                                    'Disetujui' => 'green',
                                    'Ditolak' => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$color" size="sm">{{ $surat->status_surat_pengantar }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-start gap-2">
                                <flux:button size="xs" variant="primary" wire:click="openApproveModal({{ $surat->id }})">
                                    Proses
                                </flux:button>
                                @if ($surat->status_surat_pengantar === 'Disetujui')
                                    <flux:button
                                        as="a"
                                        href="{{ route('surat-pengantar.export', $surat->id) }}" {{-- Tambahkan href --}}
                                        size="xs"
                                        variant="ghost"
                                        icon="arrow-down-tray">
                                        Ekspor
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-neutral-500">
                            Belum ada pengajuan surat pengantar dari mahasiswa.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="border-t p-4 dark:border-neutral-700">
            <flux:pagination :paginator="$this->pengajuanSurat"/>
        </div>
    </flux:card>


    {{-- Semua Modal --}}
    @if ($suratToProcess)
    <flux:modal name="approve-modal" class="md:w-[32rem]">
            <div class="space-y-6">
                {{-- Bagian Header Modal --}}
                <div>
                    <flux:heading size="lg">Proses Pengajuan Surat</flux:heading>
                    <flux:text class="mt-2">Detail pengajuan surat dari mahasiswa.</flux:text>
                </div>

                {{-- Bagian Detail Data (Read-only) --}}
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Nama Mahasiswa</span>
                        <span class="col-span-2 font-semibold">{{ $suratToProcess->mahasiswa->nama_mahasiswa }} ({{ $suratToProcess->mahasiswa->nim }})</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Tgl. Pengajuan</span>
                        <span class="col-span-2">{{ \Carbon\Carbon::parse($suratToProcess->tanggal_pengajuan_surat_pengantar)->format('d F Y') }}</span>
                    </div>
                    <hr class="dark:border-neutral-700">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Instansi Tujuan</span>
                        <span class="col-span-2">{{ $suratToProcess->lokasi_surat_pengantar }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Penerima Surat</span>
                        <span class="col-span-2">{{ $suratToProcess->penerima_surat_pengantar }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Alamat Instansi</span>
                        <span class="col-span-2">{{ $suratToProcess->alamat_surat_pengantar }}</span>
                    </div>
                    {{-- Menampilkan catatan jika statusnya ditolak --}}
                    @if($suratToProcess->status_surat_pengantar === 'Ditolak' && $suratToProcess->catatan_surat)
                        <div class="grid grid-cols-3 gap-2 text-sm">
                            <span class="text-neutral-500 font-medium">Catatan Penolakan</span>
                            <span class="col-span-2 text-red-500">{{ $suratToProcess->catatan_surat }}</span>
                        </div>
                    @endif
                </div>

                {{-- KONTEN DINAMIS BERDASARKAN STATUS --}}
                @if($suratToProcess->status_surat_pengantar === 'Diajukan')
                    {{-- Form untuk persetujuan awal --}}
                    <div>
                        <flux:input type="date" wire:model="tanggalPengambilan" label="Tanggal Pengambilan Surat" required />
                        @error('tanggalPengambilan') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                        <flux:button wire:click="openRejectModal({{ $suratToProcess->id }})" variant="danger">Tolak</flux:button>
                        <flux:button wire:click="approve" variant="primary">Setujui Pengajuan</flux:button>
                    </div>

                @elseif($suratToProcess->status_surat_pengantar === 'Disetujui')
                    {{-- Opsi untuk membatalkan persetujuan --}}
                    <div class="flex justify-end gap-3">
                        <flux:modal.close><flux:button type="button" variant="ghost">Tutup</flux:button></flux:modal.close>
                        <flux:modal.trigger :name="'cancel-approve-modal-' . $suratToProcess->id">
                            <flux:button variant="danger">Batalkan Persetujuan</flux:button>
                        </flux:modal.trigger>
                    </div>

                @elseif($suratToProcess->status_surat_pengantar === 'Ditolak')
                    {{-- Opsi untuk membatalkan penolakan dan menyetujui --}}
                    <div class="flex justify-end gap-3">
                        <flux:modal.close><flux:button type="button" variant="ghost">Tutup</flux:button></flux:modal.close>
                        <flux:modal.trigger :name="'cancel-reject-modal-' . $suratToProcess->id">
                            <flux:button variant="primary" color="yellow">Batalkan Penolakan</flux:button>
                        </flux:modal.trigger>
                        <flux:button wire:click="approve" variant="primary">
                            Langsung Setujui
                        </flux:button>
                    </div>
                @endif
            </div>
    </flux:modal>
    @endif

    @if ($suratToProcess)
        <flux:modal :name="'cancel-approve-modal-' . $suratToProcess->id" class="md:w-96">
            <div class="space-y-6 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                    <flux:icon name="exclamation-triangle" class="size-6 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">Batalkan Persetujuan?</flux:heading>
                    <flux:text class="mt-2">
                        Anda yakin ingin membatalkan persetujuan untuk surat ini? Status akan dikembalikan menjadi 'Diajukan'.
                    </flux:text>
                </div>
                <div class="flex justify-center gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Tidak</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="cancelStatus({{ $suratToProcess->id }})">
                        Ya, Batalkan
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- MODAL BARU 2: KONFIRMASI BATALKAN PENOLAKAN --}}
        <flux:modal :name="'cancel-reject-modal-' . $suratToProcess->id" class="md:w-96">
            <div class="space-y-6 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-yellow-100">
                    <flux:icon name="question-mark-circle" class="size-6 text-yellow-600" />
                </div>
                <div>
                    <flux:heading size="lg">Batalkan Penolakan?</flux:heading>
                    <flux:text class="mt-2">
                        Anda yakin ingin membatalkan penolakan untuk surat ini? Status akan dikembalikan menjadi 'Diajukan'.
                    </flux:text>
                </div>
                <div class="flex justify-center gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Tidak</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" color="yellow" wire:click="cancelStatus({{ $suratToProcess->id }})">
                        Ya, Batalkan
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Modal untuk MENOLAK pengajuan (DIPERBARUI) --}}
    <flux:modal name="reject-modal" class="md:w-900">
        {{-- HAPUS TAG <form> dari sini --}}
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Tolak Pengajuan Surat</flux:heading>
                <flux:text class="mt-2">
                    Harap berikan alasan penolakan. Catatan ini akan dapat dilihat oleh mahasiswa.
                </flux:text>
            </div>
            <div>
                <flux:textarea wire:model="rejectionNote" label="Catatan Penolakan" required />
                @error('rejectionNote') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Batal</flux:button>
                </flux:modal.close>
                {{-- TAMBAHKAN wire:click pada tombol ini --}}
                <flux:button variant="danger" wire:click="reject">Tolak Pengajuan</flux:button>
            </div>
        </div>
        {{-- HAPUS TAG </form> dari sini --}}
    </flux:modal>
</div>
