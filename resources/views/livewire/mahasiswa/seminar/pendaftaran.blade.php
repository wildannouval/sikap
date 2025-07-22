<?php

use App\Models\KerjaPraktek;
use App\Models\Ruangan;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithPagination;

new #[Title('Pendaftaran Seminar')] #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;
    use WithPagination;

    public ?KerjaPraktek $kerjaPraktek = null;
    public int $jumlahBimbinganTerverifikasi = 0;
    public bool $isEligible = false;

    // Properti Form
    public string $judul_kp_final = '';
    public string $tanggal_seminar = '';
    public ?int $ruangan_id = null;
    public string $jam_mulai = '';
    public string $jam_selesai = '';
    public $berkas_laporan_final;

    // Properti State
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';
    public ?Seminar $seminarToView = null;

    /**
     * Dijalankan saat komponen dimuat.
     */
    public function mount()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if ($mahasiswaId) {
            $this->kerjaPraktek = KerjaPraktek::with('konsultasis')
                ->where('mahasiswa_id', $mahasiswaId)
                ->where('status_pengajuan_kp', 'SPK Terbit')
                ->first();
        }

        if ($this->kerjaPraktek) {
            // Hitung jumlah bimbingan yang sudah diverifikasi
            $this->jumlahBimbinganTerverifikasi = $this->kerjaPraktek->konsultasis
                ->where('status_verifikasi', 'Diverifikasi')
                ->count();

            // Cek apakah mahasiswa memenuhi syarat (minimal 6 bimbingan)
            if ($this->jumlahBimbinganTerverifikasi >= 6) {
                $this->isEligible = true;
            }

            // Isi judul KP final dengan judul KP yang ada
            $this->judul_kp_final = $this->kerjaPraktek->judul_kp;
        }
    }

    /**
     * Mengambil daftar ruangan untuk dropdown.
     */
    #[Computed]
    public function ruangans()
    {
        return Ruangan::orderBy('nama_ruangan')->get();
    }

    /**
     * Mengambil riwayat pendaftaran seminar.
     */
    #[Computed]
    public function riwayatSeminar()
    {
        if (!$this->kerjaPraktek) {
            return Seminar::where('id', -1)->paginate(5);
        }
        return Seminar::with('ruangan')
            ->where('kerja_praktek_id', $this->kerjaPraktek->id)
            ->when($this->search, fn($q) => $q->where('judul_kp_final', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(5);
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

    /**
     * Menyimpan data pendaftaran seminar.
     */
    public function save()
    {
        // Dobel cek kelayakan di sisi server
        if (!$this->isEligible) {
            return;
        }

        $validated = $this->validate([
            'judul_kp_final' => 'required|string|max:255',
            'tanggal_seminar' => 'required|date',
            'ruangan_id' => 'required|exists:ruangans,id',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'berkas_laporan_final' => 'required|file|mimes:pdf|max:5120', // Maks 5MB
        ]);

        $validated['berkas_laporan_final'] = $this->berkas_laporan_final->store('laporan-final', 'public');
        $validated['kerja_praktek_id'] = $this->kerjaPraktek->id;

        Seminar::create($validated);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pendaftaran seminar telah terkirim dan akan diverifikasi oleh Bapendik.');
        $this->reset('tanggal_seminar', 'ruangan_id', 'jam_mulai', 'jam_selesai', 'berkas_laporan_final');
    }

    /**
     * Menghapus pendaftaran seminar yang belum diproses.
     */
    public function deleteSeminar($id)
    {
        $seminar = Seminar::findOrFail($id);

        // Otorisasi sederhana
        if ($seminar->kerjaPraktek->mahasiswa_id !== Auth::user()->mahasiswa?->id) {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Anda tidak berhak melakukan aksi ini.');
            return;
        }

        if ($seminar->status_seminar !== 'Diajukan') {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Pendaftaran yang sudah diproses tidak dapat dihapus.');
            return;
        }

        // Hapus file laporan dari storage
        Storage::disk('public')->delete($seminar->berkas_laporan_final);

        $seminar->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pendaftaran seminar berhasil dihapus.');
    }

    /**
     * Fungsi BARU untuk membuka modal detail.
     */
    public function showDetail($id)
    {
        $this->seminarToView = Seminar::findOrFail($id);
        Flux::modal('detail-seminar-modal')->show();
    }
}; ?>

<div>
    <flux:heading size="xl" level="1">Pendaftaran Seminar Kerja Praktik</flux:heading>

    @if ($kerjaPraktek)
        {{-- Cek Kelayakan Mahasiswa --}}
        @if ($isEligible)
            {{-- Tampilan Form Jika Memenuhi Syarat --}}
            <flux:subheading size="lg" class="mb-6">Silakan lengkapi form di bawah untuk mendaftar seminar.</flux:subheading>
            <flux:separator variant="subtle"/>

            <flux:card class="mt-8 md:w-2/3">
                <form wire:submit="save" enctype="multipart/form-data" class="space-y-6">
                    <flux:input wire:model="judul_kp_final" label="Judul Final Kerja Praktik" required />
                    <flux:input wire:model="berkas_laporan_final" type="file" label="Upload Laporan Final (PDF, maks 5MB)" required />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model="tanggal_seminar" type="date" label="Usulan Tanggal Seminar" required />
                        <flux:select wire:model="ruangan_id" label="Usulan Ruangan" required>
                            <option value="">Pilih Ruangan</option>
                            @foreach($this->ruangans as $ruangan)
                                <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }} ({{ $ruangan->lokasi_gedung }})</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model="jam_mulai" type="time" label="Usulan Jam Mulai" required />
                        <flux:input wire:model="jam_selesai" type="time" label="Usulan Jam Selesai" required />
                    </div>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Daftar Seminar</flux:button>
                    </div>
                </form>
            </flux:card>
        @else
            {{-- Tampilan Peringatan Jika Belum Memenuhi Syarat --}}
            <flux:separator variant="subtle" class="my-6"/>
            <flux:callout type="warning" class="mt-8">
                <p class="font-bold">Anda Belum Memenuhi Syarat Mendaftar Seminar</p>
                <p class="text-sm">Syarat untuk mendaftar seminar adalah minimal memiliki 6 catatan bimbingan yang telah diverifikasi oleh Dosen Pembimbing. Saat ini Anda memiliki **{{ $this->jumlahBimbinganTerverifikasi }} bimbingan terverifikasi**.</p>
            </flux:callout>
        @endif
        <div class="mt-8">
            <flux:heading size="lg">Riwayat Pendaftaran Seminar Anda</flux:heading>
            <flux:card class="mt-4">
                {{-- Search & Sort BARU --}}
                <div class="mb-4">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari judul seminar..." icon="magnifying-glass" />
                </div>
                <flux:table :paginate="$this->riwayatSeminar">
                    <flux:table.columns>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('judul_kp_final')">Judul Final</flux:table.column>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('tanggal_seminar')">Usulan Jadwal</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Keterangan</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayatSeminar as $seminar)
                            <flux:table.row :key="$seminar->id">
                                <flux:table.cell variant="strong">{{ Str::limit($seminar->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($seminar->tanggal_seminar)->format('d/m/Y') }}, {{ \Carbon\Carbon::parse($seminar->jam_mulai)->format('H:i') }}</flux:table.cell>
                                <flux:table.cell>
                                    @php
                                        $color = match($seminar->status_seminar) {
                                            'Diajukan' => 'yellow',
                                            'Dijadwalkan' => 'blue',
                                            'Selesai' => 'emerald',
                                            'Dinilai' => 'green',
                                            'Ditolak' => 'red',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge :color="$color" size="sm">{{ $seminar->status_seminar }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{-- Menampilkan Catatan Penolakan BARU --}}
                                    @if ($seminar->status_seminar === 'Ditolak' && $seminar->catatan)
                                        <flux:dropdown position="top" align="start">
                                            <flux:button variant="ghost" size="xs" class="!text-indigo-600 !p-0">Lihat Catatan</flux:button>
                                            <flux:popover class="max-w-xs p-3 text-sm">{{ $seminar->catatan }}</flux:popover>
                                        </flux:dropdown>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        {{-- Tombol Detail BARU --}}
                                        <flux:button size="xs" wire:click="showDetail({{ $seminar->id }})">Detail</flux:button>
                                        @if ($seminar->status_seminar === 'Diajukan')
                                            <flux:modal.trigger :name="'delete-seminar-' . $seminar->id">
                                                <flux:button variant="danger" size="xs">Hapus</flux:button>
                                            </flux:modal.trigger>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                            {{-- Modal Konfirmasi Hapus --}}
                            @if ($seminar->status_seminar === 'Diajukan')
                                <flux:modal :name="'delete-seminar-' . $seminar->id" class="md:w-96">
                                    <div class="space-y-6 text-center">
                                        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                            <flux:icon name="trash" class="size-6 text-red-600"/>
                                        </div>
                                        <div>
                                            <flux:heading size="lg">Hapus Pendaftaran Seminar?</flux:heading>
                                            <flux:text class="mt-2">
                                                Anda yakin ingin menghapus pendaftaran seminar ini?
                                            </flux:text>
                                        </div>
                                        <div class="flex justify-center gap-3">
                                            <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                            <flux:button variant="danger" wire:click="deleteSeminar({{ $seminar->id }})">Ya, Hapus</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            @endif
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-neutral-500">
                                    Anda belum pernah mendaftar seminar.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @else
        {{-- Tampilan jika tidak ada KP aktif --}}
        <flux:card class="mt-8 text-center">
            <p>Anda belum memiliki Kerja Praktik yang aktif.</p>
        </flux:card>
    @endif

    {{-- Modal BARU untuk Lihat Detail --}}
    <flux:modal name="detail-seminar-modal" class="md:w-[32rem]">
        @if ($seminarToView)
            <div class="space-y-6">
                <div><flux:heading size="lg">Detail Pendaftaran Seminar</flux:heading></div>
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Judul Final</span>
                        <span class="col-span-2 font-semibold">{{ $seminarToView->judul_kp_final }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Usulan Jadwal</span>
                        <span class="col-span-2">{{ \Carbon\Carbon::parse($seminarToView->tanggal_seminar)->format('d F Y') }}, {{ \Carbon\Carbon::parse($seminarToView->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($seminarToView->jam_selesai)->format('H:i') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Usulan Ruangan</span>
                        <span class="col-span-2">{{ $seminarToView->ruangan->nama_ruangan }}</span>
                    </div>
                    <hr class="dark:border-neutral-700">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Laporan Final</span>
                        <div class="col-span-2">
                            <flux:button as="a" href="{{ asset('storage/' . $seminarToView->berkas_laporan_final) }}" target="_blank" size="xs" icon="document-arrow-down">Unduh Laporan</flux:button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
