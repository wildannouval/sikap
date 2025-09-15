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
        $this->reset('rejectionNote', 'selectedDosenId');
        $this->resetErrorBag();
        Flux::modal('process-modal')->show();
    }

    /**
     * Aksi untuk menyetujui, SEKARANG JUGA MENYIMPAN DOSEN PEMBIMBING
     */
    public function approve()
    {
        $this->validate(
            ['selectedDosenId' => 'required|exists:dosens,id'],
            ['selectedDosenId.required' => 'Anda harus memilih Dosen Pembimbing.']
        );

        if ($this->kpToProcess) {
            $this->kpToProcess->update([
                'status_pengajuan_kp' => 'Disetujui',
                'tanggal_disetujui_kp' => now(),
                'dosen_pembimbing_id' => $this->selectedDosenId,
            ]);
            $dosenPembimbing = Dosen::find($this->selectedDosenId);
            if ($dosenPembimbing) {
                $dosenPembimbing->user->notify(new PembimbingDitugaskan($this->kpToProcess));
            }
            $this->kpToProcess->mahasiswa->user->notify(new KpDisetujui($this->kpToProcess));
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
        $this->validate(
            ['rejectionNote' => 'required|string|min:10'],
            ['rejectionNote.required' => 'Catatan penolakan wajib diisi.']
        );
        if ($this->kpToProcess) {
            $this->kpToProcess->update([
                'status_pengajuan_kp' => 'Ditolak',
                'catatan_kp' => $this->rejectionNote,
            ]);
            $this->kpToProcess->mahasiswa->user->notify(new KpDitolakKomisi($this->kpToProcess));
            Flux::modal('reject-modal')->close();
            Flux::modal('process-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah ditolak.');
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Validasi Proposal Kerja Praktik</flux:heading>
        <flux:subheading size="lg">Review kelayakan akademik proposal KP dari mahasiswa.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari mahasiswa atau judul KP..." icon="magnifying-glass" />
                </div>
                @if ($tab === 'riwayat')
                    <div class="w-full md:w-64">
                        <flux:select wire:model.live="statusFilter">
                            <option value="">Semua Status Riwayat</option>
                            <option value="Disetujui">Disetujui</option>
                            <option value="Ditolak">Ditolak</option>
                        </flux:select>
                    </div>
                @endif
            </div>

            <flux:tab.group>
                <flux:tabs wire:model.live="tab">
                    <flux:tab name="proses">Perlu Diproses</flux:tab>
                    <flux:tab name="riwayat">Riwayat Validasi</flux:tab>
                </flux:tabs>
        
                <flux:tab.panel name="proses">
                    <flux:card class="mt-4">
                        <flux:table :paginate="$this->pengajuanKp">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>Judul KP</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($this->pengajuanKp as $kp)
                                    <flux:table.row :key="$kp->id">
                                        <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>{{ Str::limit($kp->judul_kp, 50) }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button size="xs" variant="primary" wire:click="openProcessModal({{ $kp->id }})">
                                                Review & Proses
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3" class="text-center py-12 text-zinc-500">
                                            Tidak ada pengajuan KP yang perlu divalidasi.
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
        
                <flux:tab.panel name="riwayat">
                    <flux:card class="mt-4">
                        <flux:table :paginate="$this->riwayatValidasi">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>Judul KP</flux:table.column>
                                <flux:table.column>Status Akhir</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($this->riwayatValidasi as $kp)
                                    <flux:table.row :key="$kp->id">
                                        <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>{{ Str::limit($kp->judul_kp, 50) }}</flux:table.cell>
                                        <flux:table.cell>
                                            @php
                                                $color = $kp->status_pengajuan_kp === 'Disetujui' ? 'green' : 'red';
                                            @endphp
                                            <flux:badge :color="$color" size="sm">{{ $kp->status_pengajuan_kp }}</flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="3" class="text-center py-12">Belum ada riwayat validasi.</flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
        
        <div class="lg:col-span-1 space-y-8">
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Alur Kerja Dosen Komisi</h3>
                <ol class="list-decimal list-inside space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>
                        <b>Review Proposal:</b><br>
                        Periksa pengajuan baru di tab "Perlu Diproses". Klik "Review & Proses" untuk melihat detail dan mengunduh berkas proposal.
                    </li>
                    <li>
                        <b>Ambil Keputusan:</b><br>
                        Tugas utama Anda adalah menilai kelayakan proposal dari sisi akademik.
                    </li>
                    <li>
                        <b>Jika disetujui:</b> Pilih Dosen Pembimbing yang paling sesuai dengan topik, lalu klik "Setujui". Pengajuan akan diteruskan ke Bapendik untuk penerbitan SPK.
                    </li>
                    <li>
                        <b>Jika ditolak:</b> Klik "Tolak", berikan catatan yang jelas dan konstruktif bagi mahasiswa, lalu konfirmasi penolakan.
                    </li>
                     <li>
                        Semua pengajuan yang telah Anda proses akan masuk ke tab "Riwayat Validasi".
                    </li>
                </ol>
            </flux:card>
        </div>
    </div>
    
    <flux:modal name="process-modal" class="md:w-[32rem]">
        @if ($kpToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Detail Pengajuan KP</flux:heading>
                    <flux:text class="mt-2">Review detail di bawah ini sebelum memberi keputusan.</flux:text>
                </div>
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Nama Mahasiswa</span>
                        <span class="col-span-2 font-semibold">{{ $kpToProcess->mahasiswa->nama_mahasiswa }} ({{ $kpToProcess->mahasiswa->nim }})</span>
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
                <div>
                    {{-- [FIX] Tambahkan wire:model.live agar tombol Setujui langsung aktif --}}
                    <flux:select wire:model.live="selectedDosenId" label="Tugaskan Dosen Pembimbing" required>
                        <option value="">-- Pilih Dosen --</option>
                        @foreach($this->allDosens as $dosen)
                            <option value="{{ $dosen->id }}">{{ $dosen->nama_dosen }}</option>
                        @endforeach
                    </flux:select>
                    @error('selectedDosenId') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Tutup</flux:button></flux:modal.close>
                    <flux:modal.trigger name="reject-modal">
                        <flux:button variant="danger">Tolak</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger :name="'approve-confirm-modal-' . $kpToProcess->id">
                        <flux:button variant="primary" :disabled="!$selectedDosenId">Setujui</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        @endif
    </flux:modal>
    
    <flux:modal name="reject-modal" class="md:w-96">
        @if ($kpToProcess)
        <form wire:submit="reject">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Tolak Pengajuan</flux:heading>
                    <flux:text class="mt-2">Harap berikan alasan penolakan yang konstruktif untuk mahasiswa.</flux:text>
                </div>
                <div>
                    <flux:textarea wire:model="rejectionNote" label="Catatan Penolakan" required />
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger">Tolak Pengajuan</flux:button>
                </div>
            </div>
        </form>
        @endif
    </flux:modal>
    
    @if ($kpToProcess)
        <flux:modal :name="'approve-confirm-modal-' . $kpToProcess->id" class="md:w-96">
            <div class="space-y-6 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
                    <flux:icon name="check" class="size-6 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg">Setujui Pengajuan KP?</flux:heading>
                    <flux:text class="mt-2">
                        Anda yakin ingin menyetujui pengajuan KP ini? Keputusan ini akan diteruskan ke Bapendik untuk penerbitan SPK.
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