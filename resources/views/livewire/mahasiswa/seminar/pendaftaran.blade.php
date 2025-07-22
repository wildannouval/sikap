<?php

use App\Models\KerjaPraktek;
use App\Models\Ruangan;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

new #[Title('Pendaftaran Seminar')] #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public ?KerjaPraktek $kerjaPraktek = null;
    public int $jumlahBimbinganTerverifikasi = 0;
    public bool $isEligible = false;

    // Properti untuk Form
    public string $judul_kp_final = '';
    public string $tanggal_seminar = '';
    public ?int $ruangan_id = null;
    public string $jam_mulai = '';
    public string $jam_selesai = '';
    public $berkas_laporan_final;

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
            return collect();
        }
        // Tambahkan with('ruangan') untuk Eager Loading
        return Seminar::with('ruangan')->where('kerja_praktek_id', $this->kerjaPraktek->id)->latest()->get();
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

        {{-- Ganti blok placeholder tabel riwayat Anda dengan ini --}}
        <div class="mt-8">
            <flux:heading size="lg">Riwayat Pendaftaran Seminar Anda</flux:heading>
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Judul Final</flux:table.column>
                        <flux:table.column>Usulan Jadwal</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayatSeminar as $seminar)
                            <flux:table.row :key="$seminar->id">
                                <flux:table.cell variant="strong">{{ Str::limit($seminar->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($seminar->tanggal_seminar)->format('d/m/Y') }}, {{ $seminar->jam_mulai }}</flux:table.cell>
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
                                    @if ($seminar->status_seminar === 'Diajukan')
                                        <flux:modal.trigger :name="'delete-seminar-' . $seminar->id">
                                            <flux:button variant="danger" size="xs">Hapus</flux:button>
                                        </flux:modal.trigger>
                                    @else
                                        -
                                    @endif
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
</div>
