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
use App\Models\User;
use App\Notifications\PendaftaranSeminarBaru;
use Illuminate\Support\Facades\Notification;

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
            $this->jumlahBimbinganTerverifikasi = $this->kerjaPraktek->konsultasis
                ->where('status_verifikasi', 'Diverifikasi')
                ->count();
            if ($this->jumlahBimbinganTerverifikasi >= 6) {
                $this->isEligible = true;
            }
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
        if (!$this->isEligible) return;

        $validated = $this->validate([
            'judul_kp_final' => 'required|string|max:255',
            'tanggal_seminar' => 'required|date',
            'ruangan_id' => 'required|exists:ruangans,id',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required|after:jam_mulai',
            'berkas_laporan_final' => 'required|file|mimes:pdf|max:5120',
        ]);

        $validated['berkas_laporan_final'] = $this->berkas_laporan_final->store('laporan-final', 'public');
        $validated['kerja_praktek_id'] = $this->kerjaPraktek->id;

        $seminarBaru = Seminar::create($validated);
        $bapendikUsers = User::where('role', 'Bapendik')->get();
        Notification::send($bapendikUsers, new PendaftaranSeminarBaru($seminarBaru));

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pendaftaran seminar telah terkirim.');
        $this->reset('tanggal_seminar', 'ruangan_id', 'jam_mulai', 'jam_selesai', 'berkas_laporan_final');
    }

    /**
     * Menghapus pendaftaran seminar yang belum diproses.
     */
    public function deleteSeminar($id)
    {
        $seminar = Seminar::findOrFail($id);
        if ($seminar->kerjaPraktek->mahasiswa_id !== Auth::user()->mahasiswa?->id) {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Anda tidak berhak melakukan aksi ini.');
            return;
        }
        if ($seminar->status_seminar !== 'Diajukan') {
            Flux::toast(variant: 'danger', heading: 'Aksi Gagal', text: 'Pendaftaran yang sudah diproses tidak dapat dihapus.');
            return;
        }
        Storage::disk('public')->delete($seminar->berkas_laporan_final);
        $seminar->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pendaftaran seminar berhasil dihapus.');
    }

    /**
     * Fungsi BARU untuk membuka modal detail.
     */
    public function showDetail($id)
    {
        $this->seminarToView = Seminar::with('ruangan')->findOrFail($id);
        Flux::modal('detail-seminar-modal')->show();
    }

    public function konfirmasiJadwal($id, $setuju)
    {
        $seminar = Seminar::findOrFail($id);
        if ($seminar->kerjaPraktek->mahasiswa_id !== Auth::user()->mahasiswa?->id) return;

        if ($setuju) {
            $seminar->update(['status_seminar' => 'Dijadwalkan']);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jadwal seminar telah Anda konfirmasi.');
        } else {
            $seminar->update(['status_seminar' => 'Ditolak']);
            Flux::toast(variant: 'danger', heading: 'Jadwal Ditolak', text: 'Anda telah menolak jadwal yang diusulkan.');
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Pendaftaran Seminar Kerja Praktik</flux:heading>
        <flux:subheading size="lg">Daftarkan diri Anda untuk seminar setelah semua persyaratan terpenuhi.</flux:subheading>
    </div>

    @if ($kerjaPraktek)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-2 space-y-8">
                @if ($isEligible)
                    <flux:card>
                        <h3 class="text-lg font-semibold">Formulir Pendaftaran Seminar</h3>
                        <form wire:submit="save" enctype="multipart/form-data" class="mt-6 space-y-6">
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
                @endif
                <flux:card>
                     <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
                        <div>
                            <flux:heading size="lg">Riwayat Pendaftaran</flux:heading>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari judul..." icon="magnifying-glass" class="flex-1" />
                        </div>
                    </div>
                    <flux:table :paginate="$this->riwayatSeminar">
                        <flux:table.columns>
                            <flux:table.column class="cursor-pointer" wire:click="sortBy('judul_kp_final')">Judul Final</flux:table.column>
                            <flux:table.column class="cursor-pointer" wire:click="sortBy('tanggal_seminar')">Jadwal</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Aksi</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->riwayatSeminar as $seminar)
                                <flux:table.row :key="$seminar->id">
                                    <flux:table.cell variant="strong">{{ Str::limit($seminar->judul_kp_final, 40) }}</flux:table.cell>
                                    <flux:table.cell>
                                        {{ \Carbon\Carbon::parse($seminar->tanggal_seminar)->format('d/m/Y') }}, {{ \Carbon\Carbon::parse($seminar->jam_mulai)->format('H:i') }}
                                        <span class="block text-xs text-zinc-500">{{ $seminar->ruangan->nama_ruangan }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @php
                                            $color = match($seminar->status_seminar) {
                                                'Diajukan' => 'yellow',
                                                'Menunggu Konfirmasi' => 'orange',
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
                                        <div class="flex items-center gap-2">
                                            <flux:button size="xs" wire:click="showDetail({{ $seminar->id }})">Detail</flux:button>
                                            @if ($seminar->status_seminar === 'Diajukan')
                                                <flux:modal.trigger :name="'delete-seminar-' . $seminar->id">
                                                    <flux:button variant="danger" size="xs">Hapus</flux:button>
                                                </flux:modal.trigger>
                                            @elseif ($seminar->status_seminar === 'Menunggu Konfirmasi')
                                                {{-- [FIX 1] Menambahkan tombol Tolak --}}
                                                <flux:button size="xs" variant="primary" wire:click="konfirmasiJadwal({{ $seminar->id }}, true)">Setujui</flux:button>
                                                <flux:button size="xs" variant="danger" wire:click="konfirmasiJadwal({{ $seminar->id }}, false)">Tolak</flux:button>
                                            @elseif ($seminar->status_seminar === 'Dijadwalkan')
                                                {{-- [FIX 2] Mengganti variant="secondary" menjadi "ghost" --}}
                                                <flux:button as="a" href="{{ route('bap.cetak', $seminar->id) }}" target="_blank" variant="ghost" size="xs" icon="document-arrow-down" />
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>

                                @if ($seminar->status_seminar === 'Diajukan')
                                    <flux:modal :name="'delete-seminar-' . $seminar->id" class="md:w-96">
                                        <div class="space-y-6 text-center">
                                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                                <flux:icon name="trash" class="size-6 text-red-600"/>
                                            </div>
                                            <div>
                                                <flux:heading size="lg">Hapus Pendaftaran?</flux:heading>
                                                <flux:text class="mt-2">Anda yakin ingin menghapus pendaftaran seminar ini?</flux:text>
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
                                    <flux:table.cell colspan="4">
                                        <div class="text-center py-12">
                                            <flux:icon name="document-magnifying-glass" class="size-10 mx-auto text-zinc-400"/>
                                            <h3 class="mt-2 text-md font-medium text-zinc-800 dark:text-zinc-200">Belum Ada Pendaftaran</h3>
                                            @if($isEligible)
                                            <p class="mt-1 text-sm text-zinc-500">Gunakan formulir di atas untuk mendaftar seminar.</p>
                                            @else
                                            <p class="mt-1 text-sm text-zinc-500">Penuhi syarat bimbingan terlebih dahulu untuk mendaftar.</p>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </div>

            <div class="lg:col-span-1 space-y-8">
                <flux:card>
                    <h3 class="text-lg font-semibold mb-4">Syarat Pendaftaran</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">Anda harus memenuhi syarat minimal bimbingan untuk dapat mendaftar seminar.</p>
                    <div class="p-4 rounded-lg {{ $isEligible ? 'bg-green-50 border-green-300 dark:bg-green-900/20 dark:border-green-700' : 'bg-yellow-50 border-yellow-300 dark:bg-yellow-900/20 dark:border-yellow-700' }} border">
                        <div class="flex items-center gap-3">
                            <flux:icon name="{{ $isEligible ? 'check-circle' : 'exclamation-circle' }}" class="size-8 {{ $isEligible ? 'text-green-500' : 'text-yellow-500' }}"/>
                            <div>
                                <p class="font-semibold">Minimal 6x Bimbingan Terverifikasi</p>
                                <p class="text-sm">Bimbingan Anda saat ini: <span class="font-bold">{{ $jumlahBimbinganTerverifikasi }}x terverifikasi</span></p>
                            </div>
                        </div>
                    </div>
                </flux:card>
                
                <flux:card>
                    <h3 class="text-lg font-semibold mb-4">Makna Status Seminar</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <flux:badge color="yellow" class="mt-0.5">Diajukan</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Pendaftaran terkirim dan menunggu penjadwalan dari Bapendik.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:badge color="orange" class="mt-0.5 whitespace-nowrap">Menunggu Konfirmasi</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Bapendik menyarankan jadwal baru. Anda perlu menyetujui jadwal ini.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:badge color="blue" class="mt-0.5">Dijadwalkan</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Seminar telah dijadwalkan. Silakan unduh Berita Acara (BAP).</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:badge color="emerald" class="mt-0.5">Selesai</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Seminar telah dilaksanakan dan menunggu penilaian akhir dari dosen.</p>
                        </div>
                         <div class="flex items-start gap-3">
                            <flux:badge color="green" class="mt-0.5">Dinilai</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Seminar telah selesai dinilai. Lihat nilai akhir Anda di menu "Nilai KP".</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:badge color="red" class="mt-0.5">Ditolak</flux:badge>
                            <p class="text-zinc-600 dark:text-zinc-400">Pendaftaran/jadwal ditolak. Periksa catatan dari Bapendik.</p>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
        
    @else
        <flux:separator variant="subtle" class="my-6"/>
        <flux:card class="mt-8 text-center">
            <flux:icon name="information-circle" class="mx-auto size-12 text-zinc-400" />
            <p class="mt-4 font-semibold">Kerja Praktik Belum Aktif</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Anda belum bisa mendaftar seminar karena belum memiliki Kerja Praktik yang statusnya aktif.</p>
            <flux:button as="a" href="{{ route('kp.pengajuan') }}" variant="primary" size="sm" class="mt-4">
                Lihat Status Pengajuan KP
            </flux:button>
        </flux:card>
    @endif

    {{-- Modal untuk Detail Seminar --}}
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
                        <span class="text-neutral-500">Jadwal Seminar</span>
                        <span class="col-span-2">{{ \Carbon\Carbon::parse($seminarToView->tanggal_seminar)->format('d F Y') }}, {{ \Carbon\Carbon::parse($seminarToView->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($seminarToView->jam_selesai)->format('H:i') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Ruangan</span>
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