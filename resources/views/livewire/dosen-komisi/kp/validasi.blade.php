<?php

use App\Models\KerjaPraktek;
use App\Models\Dosen;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Notifications\KpDitolakKomisi;
use App\Models\User;
use App\Notifications\KpDisetujui;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PembimbingDitugaskan;

new #[Title('Validasi Proposal KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti untuk state halaman
    public string $tab = 'proses';
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Properti untuk Modal Detail & Aksi
    public ?KerjaPraktek $kpToProcess = null;

    // Properti untuk Modal Penolakan
    public string $rejectionNote = '';

    // Properti BARU untuk menampung ID dosen yang dipilih dari dropdown
    public ?int $selectedDosenId = null;

    // Hook untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'tab'])) {
            $this->resetPage();
        }
    }

    // Fungsi untuk sorting
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // Base query untuk menghindari duplikasi kode
    private function getBaseQuery()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan'])
            ->when($this->search, function ($query) {
                // Tambahkan pengelompokan where di sini
                $query->where(function ($subQuery) {
                    $subQuery->where('judul_kp', 'like', '%' . $this->search . '%')
                        ->orWhereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%'));
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    /**
     * Properti BARU untuk mengambil semua data dosen sebagai pilihan
     */
    #[Computed]
    public function allDosens()
    {
        return Dosen::orderBy('nama_dosen')->get();
    }

    #[Computed]
    public function pengajuanKp()
    {
        return $this->getBaseQuery()
            ->where('status_pengajuan_kp', 'Proses di Komisi')
            ->paginate(10, ['*'], 'prosesPage');
    }

    /**
     * Data untuk tab "Riwayat Validasi".
     */
    #[Computed]
    public function riwayatValidasi()
    {
        return $this->getBaseQuery()
            ->whereIn('status_pengajuan_kp', ['Disetujui', 'Ditolak'])
            ->when($this->statusFilter, fn($q) => $q->where('status_pengajuan_kp', $this->statusFilter))
            ->paginate(10, ['*'], 'riwayatPage');
    }

    /**
     * Membuka modal detail untuk diproses.
     */
    public function openProcessModal($id)
    {
        $this->kpToProcess = KerjaPraktek::findOrFail($id);
        $this->reset('rejectionNote');
        Flux::modal('process-modal')->show();
    }

    /**
     * Aksi untuk menyetujui, SEKARANG JUGA MENYIMPAN DOSEN PEMBIMBING
     */
    public function approve()
    {
        // Tambahkan validasi untuk memastikan dosen telah dipilih
        $this->validate([
            'selectedDosenId' => 'required|exists:dosens,id'
        ]);

        if ($this->kpToProcess) {
            $this->kpToProcess->update([
                'status_pengajuan_kp' => 'Disetujui',
                'tanggal_disetujui_kp' => now(),
                'dosen_pembimbing_id' => $this->selectedDosenId,
            ]);
            // 1. Cari Dosen Pembimbing yang baru ditugaskan
            $dosenPembimbing = Dosen::find($this->selectedDosenId);
            if ($dosenPembimbing) {
                // 2. Kirim notifikasi ke Dosen Pembimbing tersebut
                $dosenPembimbing->user->notify(new PembimbingDitugaskan($this->kpToProcess));
            }
            // Kirim notifikasi ke Mahasiswa
            $this->kpToProcess->mahasiswa->user->notify(new KpDisetujui($this->kpToProcess));
// Kirim notifikasi ke Bapendik
            $bapendikUsers = User::where('role', 'Bapendik')->get();
            Notification::send($bapendikUsers, new KpDisetujui($this->kpToProcess));

            Flux::modal('approve-confirm-modal-' . $this->kpToProcess->id)->close();
            Flux::modal('process-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah disetujui dan pembimbing telah ditugaskan.');
        }
    }

    /**
     * Aksi untuk menolak pengajuan KP.
     */
    public function reject()
    {
        $this->validate(['rejectionNote' => 'required|string|min:10']);
        if ($this->kpToProcess) {
            $this->kpToProcess->update([
                'status_pengajuan_kp' => 'Ditolak',
                'catatan_kp' => $this->rejectionNote,
            ]);
            Flux::modal('reject-modal')->close();
            Flux::modal('process-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah ditolak.');
        }
        $this->kpToProcess->mahasiswa->user->notify(new KpDitolakKomisi($this->kpToProcess));    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Validasi Proposal Kerja Praktik</flux:heading>
            <flux:subheading size="lg" class="mb-6">Review kelayakan proposal KP dari mahasiswa.</flux:subheading>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    {{-- Input Search & Filter BARU --}}
    <div class="mt-6 flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari berdasarkan nama mahasiswa atau judul KP..." icon="magnifying-glass" />
        </div>
        @if ($tab === 'riwayat')
            <div class="w-full md:w-64">
                <flux:select wire:model.live="statusFilter" placeholder="Filter status riwayat">
                    <option value="">Semua Status Riwayat</option>
                    <option value="Disetujui">Disetujui</option>
                    <option value="Ditolak">Ditolak</option>
                </flux:select>
            </div>
        @endif
    </div>

    {{-- Grup Tab --}}
    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="proses">Perlu Diproses</flux:tab>
            <flux:tab name="riwayat">Riwayat Validasi</flux:tab>
        </flux:tabs>

        {{-- Panel untuk Tab "Perlu Diproses" --}}
        <flux:tab.panel name="proses">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->pengajuanKp">
                    <flux:table.columns>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('mahasiswa.nama_mahasiswa')">Nama Mahasiswa</flux:table.column>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('judul_kp')">Judul KP</flux:table.column>
                        <flux:table.column>Berkas</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->pengajuanKp as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button as="a" href="{{ asset('storage/' . $kp->proposal_kp) }}" target="_blank" size="xs" icon="document-arrow-down">Proposal</flux:button>
                                        <flux:button as="a" href="{{ asset('storage/' . $kp->surat_keterangan_kp) }}" target="_blank" size="xs" icon="document-arrow-down">Surat Ket.</flux:button>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="primary" wire:click="openProcessModal({{ $kp->id }})">
                                        Proses
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-neutral-500">
                                    Tidak ada pengajuan KP yang perlu divalidasi.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>

        {{-- Panel untuk Tab "Riwayat Validasi" --}}
        <flux:tab.panel name="riwayat">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->riwayatValidasi">
                    <flux:table.columns>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('mahasiswa.nama_mahasiswa')">Nama Mahasiswa</flux:table.column>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('judul_kp')">Judul KP</flux:table.column>
                        <flux:table.column>Status Akhir</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayatValidasi as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>
                                    @php
                                        $color = $kp->status_pengajuan_kp === 'Disetujui' ? 'green' : 'red';
                                    @endphp
                                    <flux:badge :color="$color" size="sm">{{ $kp->status_pengajuan_kp }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center">Belum ada riwayat validasi.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Modal untuk Proses (Setuju/Tolak) --}}
    <flux:modal name="process-modal" class="md:w-[32rem]">
        @if ($kpToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Detail Pengajuan KP</flux:heading>
                    <flux:text class="mt-2">Review detail di bawah ini sebelum memberi keputusan.</flux:text>
                </div>

                {{-- Detail Data Lengkap --}}
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Nama Mahasiswa</span>
                        <span class="col-span-2 font-semibold">{{ $kpToProcess->mahasiswa->nama_mahasiswa }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">NIM</span>
                        <span class="col-span-2 font-semibold">{{ $kpToProcess->mahasiswa->nim }}</span>
                    </div>
                    <hr class="dark:border-neutral-700">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Judul KP</span>
                        <span class="col-span-2">{{ $kpToProcess->judul_kp }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Lokasi KP</span>
                        <span class="col-span-2">{{ $kpToProcess->lokasi_kp }}</span>
                    </div>
                    <hr class="dark:border-neutral-700">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Berkas</span>
                        <div class="col-span-2 flex items-center gap-2">
                            <flux:button as="a" href="{{ asset('storage/' . $kpToProcess->proposal_kp) }}" target="_blank" size="xs" icon="document-arrow-down">Unduh Proposal</flux:button>
                            <flux:button as="a" href="{{ asset('storage/' . $kpToProcess->surat_keterangan_kp) }}" target="_blank" size="xs" icon="document-arrow-down">Unduh Surat Ket.</flux:button>
                        </div>
                    </div>
                </div>

                {{-- Dropdown BARU untuk memilih Dosen Pembimbing --}}
                <div>
                    <flux:select wire:model="selectedDosenId" label="Pilih Dosen Pembimbing" required>
                        <option value="">-- Pilih Dosen --</option>
                        @foreach($this->allDosens as $dosen)
                            <option value="{{ $dosen->id }}">{{ $dosen->nama_dosen }}</option>
                        @endforeach
                    </flux:select>
                    @error('selectedDosenId') <span class="mt-1 text-sm text-red-500">Dosen pembimbing wajib dipilih.</span> @enderror
                </div>

                {{-- Tombol Aksi --}}
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Tutup</flux:button></flux:modal.close>
                    <flux:modal.trigger name="reject-modal">
                        <flux:button variant="danger">Tolak</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger :name="'approve-confirm-modal-' . $kpToProcess->id">
                        <flux:button variant="primary">Setujui</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal untuk konfirmasi penolakan --}}
    <flux:modal name="reject-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Tolak Pengajuan</flux:heading>
                <flux:text class="mt-2">Harap berikan alasan penolakan (minimal 10 karakter).</flux:text>
            </div>
            <div>
                <flux:textarea wire:model="rejectionNote" label="Catatan Penolakan" required />
                @error('rejectionNote') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button wire:click="reject" variant="danger">Tolak Pengajuan</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal BARU untuk konfirmasi persetujuan --}}
    @if ($kpToProcess)
        <flux:modal :name="'approve-confirm-modal-' . $kpToProcess->id" class="md:w-96">
            <div class="space-y-6 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
                    <flux:icon name="check" class="size-6 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg">Setujui Pengajuan KP?</flux:heading>
                    <flux:text class="mt-2">
                        Anda yakin ingin menyetujui pengajuan KP ini? Keputusan ini akan diteruskan ke Bapendik.
                    </flux:text>
                </div>
                <div class="flex justify-center gap-3">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button variant="primary" wire:click="approve">Ya, Setujui</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
