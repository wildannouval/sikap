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
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Pengajuan Surat Pengantar</flux:heading>
            <flux:subheading size="lg" class="mb-6">Riwayat pengajuan surat pengantar kerja praktik Anda.</flux:subheading>
        </div>
    </div>

    <flux:separator/>

    <flux:card class="space-y-6 mt-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <flux:heading size="lg" class="text-lg">Daftar Pengajuan Surat Pengantar</flux:heading>
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
                            @elseif($surat->status_surat_pengantar === 'Disetujui' && $surat->tanggal_pengambilan_surat_pengantar)
                                <span class="text-sm">Bisa diambil pada:<br><span class="font-semibold">{{ \Carbon\Carbon::parse($surat->tanggal_pengambilan_surat_pengantar)->format('d F Y') }}</span></span>
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
                        <flux:table.cell colspan="5" class="text-center text-neutral-500">
                            Tidak ada data ditemukan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="create-pengajuan-surat-pengantar" class="md:w-900">
        <div class="space-y-6">
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

            <div class="flex">
                <flux:spacer/>

                <flux:button type="submit" variant="primary" wire:click="save">Kirim Pengajuan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
