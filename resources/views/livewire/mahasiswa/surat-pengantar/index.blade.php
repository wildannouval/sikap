<?php

use App\Models\SuratPengantar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use App\Models\User;
use App\Notifications\PengajuanSuratBaru;
use Illuminate\Support\Facades\Notification;

new #[Title('Surat Pengantar')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti untuk state halaman
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'tanggal_pengajuan_surat_pengantar';
    #[Url]
    public string $sortDirection = 'desc';

    // Properti Form
    public string $lokasi_surat_pengantar = '';
    public string $penerima_surat_pengantar = '';
    public string $alamat_surat_pengantar = '';
    public string $tembusan_surat_pengantar = '';

    // Hook untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function riwayatSurat()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if (!$mahasiswaId) {
            return SuratPengantar::where('id', -1)->paginate(5);
        }

        return SuratPengantar::where('mahasiswa_id', $mahasiswaId)
            ->when($this->search, function ($query) {
                $query->where('lokasi_surat_pengantar', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status_surat_pengantar', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(5);
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

    public function save(): void
    {
        $validated = $this->validate([
            'lokasi_surat_pengantar' => ['required', 'string', 'max:255'],
            'penerima_surat_pengantar' => ['required', 'string', 'max:255'],
            'alamat_surat_pengantar' => ['required', 'string'],
            'tembusan_surat_pengantar' => ['nullable', 'string', 'max:255'],
        ]);

        $mahasiswa = Auth::user()->mahasiswa;
        if (!$mahasiswa) {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Profil mahasiswa tidak ditemukan.');
            return;
        }

        $validated['mahasiswa_id'] = $mahasiswa->id;
        $validated['tanggal_pengajuan_surat_pengantar'] = now();

        $suratBaru = SuratPengantar::create($validated);

        $bapendikUsers = User::where('role', 'Bapendik')->get();
        if ($bapendikUsers->isNotEmpty()) {
            Notification::send($bapendikUsers, new PengajuanSuratBaru($suratBaru));
        }

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat pengantar berhasil diajukan.');

        $this->reset();
        Flux::modal('create-pengajuan-surat-pengantar')->close();
    }

    public function delete($id)
    {
        $surat = SuratPengantar::findOrFail($id);
        if (Auth::user()->mahasiswa?->id !== $surat->mahasiswa_id) {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Anda tidak berhak melakukan aksi ini.');
            return;
        }

        if ($surat->status_surat_pengantar !== 'Diajukan') {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Pengajuan yang sudah diproses tidak dapat dihapus.');
            return;
        }

        $surat->delete();
        Flux::modal('delete-surat-' . $id)->close();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan surat berhasil dihapus.');
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Pengajuan Surat Pengantar</flux:heading>
        <flux:subheading size="lg">Ajukan dan lacak status surat pengantar kerja praktik Anda di sini.</flux:subheading>
    </div>

    {{-- [START] PERUBAHAN LAYOUT MENJADI DUA KOLOM --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        {{-- Kolom Kiri (Utama) --}}
        <div class="lg:col-span-2">
            <flux:card class="space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex-1">
                        <flux:heading size="lg" class="text-lg">Daftar Riwayat Pengajuan</flux:heading>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                        <flux:select wire:model.live="statusFilter" size="sm" class="w-full sm:w-40">
                            <option value="">Semua Status</option>
                            <option value="Diajukan">Diajukan</option>
                            <option value="Siap Diambil">Siap Diambil</option>
                            <option value="Disetujui">Disetujui</option>
                            <option value="Ditolak">Ditolak</option>
                        </flux:select>
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari instansi..."
                            size="sm"
                            icon="magnifying-glass"
                            class="w-full sm:w-56"
                        />
                        <flux:modal.trigger name="create-pengajuan-surat-pengantar">
                            <flux:button variant="primary" icon="plus" size="sm">Buat Pengajuan</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
        
                <flux:separator variant="subtle"/>
        
                <flux:table :paginate="$this->riwayatSurat">
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$this->sortField === 'lokasi_surat_pengantar'" :direction="$this->sortDirection" wire:click="sortBy('lokasi_surat_pengantar')">Instansi Tujuan</flux:table.column>
                        <flux:table.column sortable :sorted="$this->sortField === 'tanggal_pengajuan_surat_pengantar'" :direction="$this->sortDirection" wire:click="sortBy('tanggal_pengajuan_surat_pengantar')">Tgl. Pengajuan</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Keterangan</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
        
                    <flux:table.rows>
                        @forelse ($this->riwayatSurat as $surat)
                            <flux:table.row :key="$surat->id">
                                <flux:table.cell variant="strong">{{ $surat->lokasi_surat_pengantar }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($surat->tanggal_pengajuan_surat_pengantar)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    @php
                                        $color = match($surat->status_surat_pengantar) {
                                            'Diajukan' => 'yellow',
                                            'Siap Diambil' => 'blue',
                                            'Disetujui' => 'green',
                                            'Ditolak' => 'red',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge :color="$color" size="sm">{{ $surat->status_surat_pengantar }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($surat->status_surat_pengantar === 'Ditolak' && $surat->catatan_surat)
                                        <flux:dropdown position="top" align="start">
                                            <flux:button variant="ghost" size="xs" class="!text-indigo-600 !p-0">Lihat Catatan</flux:button>
                                            <flux:popover class="max-w-xs p-3 text-sm">{{ $surat->catatan_surat }}</flux:popover>
                                        </flux:dropdown>
                                    @elseif($surat->status_surat_pengantar === 'Siap Diambil' && $surat->tanggal_pengambilan_surat_pengantar)
                                        <span class="text-sm">Ambil pada:<br><span class="font-semibold">{{ \Carbon\Carbon::parse($surat->tanggal_pengambilan_surat_pengantar)->format('d F Y') }}</span></span>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($surat->status_surat_pengantar === 'Diajukan')
                                        <flux:modal.trigger :name="'delete-surat-' . $surat->id">
                                            <flux:button variant="danger" size="xs">Hapus</flux:button>
                                        </flux:modal.trigger>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
        
                            @if ($surat->status_surat_pengantar === 'Diajukan')
                                <flux:modal :name="'delete-surat-' . $surat->id" class="md:w-96">
                                    <div class="space-y-6 text-center">
                                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                            <flux:icon name="trash" class="size-6 text-red-600"/>
                                        </div>
                                        <div>
                                            <flux:heading size="lg">Hapus Pengajuan?</flux:heading>
                                            <flux:text class="mt-2">
                                                Anda yakin ingin menghapus pengajuan untuk instansi <span
                                                    class="font-bold">{{ $surat->lokasi_surat_pengantar }}</span>? Tindakan ini
                                                tidak dapat dibatalkan.
                                            </flux:text>
                                        </div>
                                        <div class="flex justify-center gap-3">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">Batal</flux:button>
                                            </flux:modal.close>
                                            <flux:button variant="danger" wire:click="delete({{ $surat->id }})">
                                                Ya, Hapus
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            @endif
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <div class="text-center py-12">
                                        <flux:icon name="document-magnifying-glass" class="size-10 mx-auto text-zinc-400"/>
                                        <h3 class="mt-2 text-md font-medium text-zinc-800 dark:text-zinc-200">Belum Ada Pengajuan</h3>
                                        <p class="mt-1 text-sm text-zinc-500">Klik "Buat Pengajuan" untuk memulai.</p>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>

        {{-- Kolom Kanan (Informasi) --}}
        <div class="lg:col-span-1 space-y-6">
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Makna Status</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <flux:badge color="yellow" class="mt-0.5">Diajukan</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Pengajuan Anda sedang dalam antrian untuk diperiksa oleh Bapendik.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:badge color="green" class="mt-0.5">Disetujui</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Pengajuan disetujui, surat sedang disiapkan dan akan segera ditandatangani.</p>
                    </div>
                     <div class="flex items-start gap-3">
                        <flux:badge color="blue" class="mt-0.5">Siap Diambil</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Surat fisik sudah selesai, ditandatangani, dan dapat diambil di Bapendik.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:badge color="red" class="mt-0.5">Ditolak</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Pengajuan ditolak. Silakan cek "Lihat Catatan" pada tabel untuk melihat alasan penolakan.</p>
                    </div>
                </div>
            </flux:card>

             <flux:card>
                <h3 class="text-lg font-semibold mb-4">Tata Cara Pengajuan</h3>
                <ol class="list-decimal list-inside space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>Klik tombol <span class="font-semibold text-primary-600 dark:text-primary-400">"Buat Pengajuan"</span> di atas.</li>
                    <li>Isi formulir dengan data yang lengkap dan benar, terutama nama instansi, penerima, dan alamat.</li>
                    <li>Setelah mengirim, pantau status pengajuan Anda secara berkala pada tabel riwayat.</li>
                    <li>Jika status sudah <flux:badge color="blue" size="xs">Siap Diambil</flux:badge>, Anda dapat mengambil surat fisik di kantor Bapendik.</li>
                    <li>Jika <flux:badge color="red" size="xs">Ditolak</flux:badge>, periksa catatan, perbaiki kesalahan, dan ajukan kembali.</li>
                </ol>
            </flux:card>
        </div>
    </div>
    {{-- [END] PERUBAHAN LAYOUT --}}


    <flux:modal name="create-pengajuan-surat-pengantar" class="md:w-900">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Formulir Pengajuan Surat Pengantar</flux:heading>
                <flux:text class="mt-2">Isi detail di bawah ini untuk meminta surat pengantar KP.</flux:text>
            </div>
    
            <flux:input
                wire:model="lokasi_surat_pengantar"
                label="Lokasi Penelitian / Instansi Tujuan"
                placeholder="Contoh: PT. Teknologi Maju Bersama"
                required/>
    
            <flux:input
                wire:model="penerima_surat_pengantar"
                label="Penerima Surat (Yth. Bapak/Ibu ...)"
                placeholder="Contoh: Yth. Manajer HRD"
                required/>
    
            <flux:textarea
                wire:model="alamat_surat_pengantar"
                label="Alamat Lengkap Instansi"
                placeholder="Contoh: Jl. Jenderal Sudirman No. 45, Jakarta Pusat"
                required/>
    
            <flux:input
                wire:model="tembusan_surat_pengantar"
                label="Tembusan (jika ada)"
                placeholder="Contoh: Kepala Departemen Teknik Informatika"/>
    
            <div class="flex justify-end gap-3">
                 <flux:modal.close>
                    <flux:button type="button" variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Kirim Pengajuan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>