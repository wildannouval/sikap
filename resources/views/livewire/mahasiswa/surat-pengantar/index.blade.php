<?php

use App\Models\SuratPengantar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new #[Title('Surat Pengantar')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti Form
    public string $lokasi_surat_pengantar = '';
    public string $penerima_surat_pengantar = '';
    public string $alamat_surat_pengantar = '';
    public string $tembusan_surat_pengantar = '';

    #[Computed]
    public function riwayatSurat()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if (!$mahasiswaId) {
            return SuratPengantar::where('id', -1)->paginate(5);
        }

        return SuratPengantar::where('mahasiswa_id', $mahasiswaId)
            ->latest()
            ->paginate(5);
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

        SuratPengantar::create($validated);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat pengantar berhasil diajukan.');

        $this->reset();
        Flux::modal('create-pengajuan-surat-pengantar')->close();
//        $this->redirectRoute('surat-pengantar.index', navigate: true);
    }

    public function delete($id)
    {
        $surat = SuratPengantar::findOrFail($id);
        if (Auth::user()->mahasiswa->id !== $surat->mahasiswa_id) {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Anda tidak berhak melakukan aksi ini.');
            return;
        }

        // Hanya izinkan hapus jika status masih 'Diajukan'
        if ($surat->status_surat_pengantar !== 'Diajukan') {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Pengajuan yang sudah diproses tidak dapat dihapus.');
            return;
        }

        $surat->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan surat berhasil dihapus.');
    }

    public function showCatatan($id)
    {
        $surat = SuratPengantar::findOrFail($id);
        $this->catatanToShow = $surat->catatan_surat;
        Flux::modal('catatan-modal')->show();
    }

}; ?>

<div>
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Pengajuan Surat Pengantar</flux:heading>
            <flux:subheading size="lg" class="mb-6">Riwayat pengajuan surat pengantar kerja praktik Anda.
            </flux:subheading>
        </div>
        <flux:modal.trigger name="create-pengajuan-surat-pengantar">
            <flux:button variant="primary" icon="plus">Buat Pengajuan</flux:button>
        </flux:modal.trigger>
    </div>
    <flux:separator variant="subtle"/>

    <flux:card class="mt-8">
        {{-- Kita akan memisahkan tabel dan paginasi --}}
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Instansi Tujuan</flux:table.column>
                <flux:table.column>Tgl. Pengajuan</flux:table.column>
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
                                    'Disetujui' => 'green',
                                    'Ditolak' => 'red',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$color" size="sm">{{ $surat->status_surat_pengantar }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{-- PERBAIKAN 2: Menggunakan Popover untuk Catatan --}}
                            @if ($surat->status_surat_pengantar === 'Ditolak' && $surat->catatan_surat)
                                <flux:dropdown position="top" align="start">
                                    <flux:button
                                        variant="primary"
                                        color="yellow"
                                        size="xs">
                                        Lihat Catatan Penolakan
                                    </flux:button>
                                    <flux:popover class="max-w-xs p-3 text-sm">
                                        {{ $surat->catatan_surat }}
                                    </flux:popover>
                                </flux:dropdown>
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

                    {{-- PERBAIKAN 1: Isi Modal Hapus --}}
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
                            Anda belum pernah mengajukan surat pengantar.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- PERBAIKAN 3: Menampilkan Paginasi secara Manual --}}
        <div class="border-t p-4 dark:border-neutral-700">
            <flux:pagination :paginator="$this->riwayatSurat"/>
        </div>
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
