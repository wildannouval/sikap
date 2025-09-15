<?php

use App\Models\SuratPengantar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Notifications\SuratPengantarDisetujui;
use App\Notifications\SuratPengantarDitolak;
use App\Notifications\SuratSiapDiambil;

new #[Title('Validasi Surat Pengantar')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $tab = 'proses';
    #[Url(as: 'q')]
    public string $search = '';

    // Properti untuk Modal
    public ?SuratPengantar $suratToProcess = null;
    public string $nomor_surat = '';
    public string $tanggal_pengambilan = '';
    public string $rejectionNote = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedTab() { $this->resetPage(); }

    private function getBaseQuery()
    {
        return SuratPengantar::with('mahasiswa')
            ->when($this->search, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%'))
                    ->orWhere('lokasi_surat_pengantar', 'like', '%' . $this->search . '%');
            })
            ->latest('tanggal_pengajuan_surat_pengantar');
    }

    #[Computed]
    public function perluDiproses()
    {
        return $this->getBaseQuery()->where('status_surat_pengantar', 'Diajukan')->paginate(10, ['*'], 'prosesPage');
    }

    #[Computed]
    public function disetujui()
    {
        // [FIX] Tab ini sekarang akan menampilkan surat yang sudah disetujui
        return $this->getBaseQuery()->where('status_surat_pengantar', 'Disetujui')->paginate(10, ['*'], 'disetujuiPage');
    }

    #[Computed]
    public function riwayat()
    {
        // [FIX] Riwayat sekarang berisi yang Siap Diambil dan Ditolak
        return $this->getBaseQuery()->whereIn('status_surat_pengantar', ['Siap Diambil', 'Ditolak'])->paginate(10, ['*'], 'riwayatPage');
    }

    public function openProcessModal($id)
    {
        $this->suratToProcess = SuratPengantar::with('mahasiswa')->findOrFail($id);
        $this->reset('nomor_surat', 'rejectionNote');
        $this->resetErrorBag();
        Flux::modal('process-modal')->show();
    }

    public function openPickupDateModal($id)
    {
        $this->suratToProcess = SuratPengantar::with('mahasiswa')->findOrFail($id);
        $this->reset('tanggal_pengambilan');
        $this->tanggal_pengambilan = now()->format('Y-m-d');
        $this->resetErrorBag();
        Flux::modal('pickup-date-modal')->show();
    }

    public function process()
    {
        $this->validate(['nomor_surat' => 'required|string|max:255'], ['nomor_surat.required' => 'Nomor surat wajib diisi.']);
        if ($this->suratToProcess) {
            // [FIX] Tahap proses mengubah status menjadi 'Disetujui'
            $this->suratToProcess->update([
                'status_surat_pengantar' => 'Disetujui',
                'nomor_surat' => $this->nomor_surat,
                'tanggal_disetujui_surat_pengantar' => now(),
            ]);
            
            Flux::modal('process-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat telah disetujui dan masuk ke antrian cetak.');
        }
    }

    public function reject()
    {
        $this->validate(['rejectionNote' => 'required|string|min:10'], [
            'rejectionNote.required' => 'Catatan penolakan wajib diisi.',
            'rejectionNote.min' => 'Catatan penolakan minimal harus 10 karakter.',
        ]);

        if ($this->suratToProcess) {
            $this->suratToProcess->update([
                'status_surat_pengantar' => 'Ditolak',
                'catatan_surat' => $this->rejectionNote,
            ]);
            $this->suratToProcess->mahasiswa->user->notify(new SuratPengantarDitolak($this->suratToProcess));
            Flux::modal('process-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat pengantar telah ditolak.');
        }
    }

    public function savePickupDate()
    {
        $this->validate(['tanggal_pengambilan' => 'required|date'], ['tanggal_pengambilan.required' => 'Tanggal pengambilan wajib diisi.']);
        if ($this->suratToProcess) {
            // [FIX] Tahap input tanggal mengubah status menjadi 'Siap Diambil'
            $this->suratToProcess->update([
                'status_surat_pengantar' => 'Siap Diambil',
                'tanggal_pengambilan_surat_pengantar' => $this->tanggal_pengambilan,
            ]);
            
            // Mengirim notifikasi bahwa surat sudah siap diambil
            $this->suratToProcess->mahasiswa->user->notify(new SuratSiapDiambil($this->suratToProcess));

            Flux::modal('pickup-date-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tanggal pengambilan berhasil disimpan. Mahasiswa telah diberitahu.');
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Validasi Surat Pengantar</flux:heading>
        <flux:subheading size="lg">Kelola alur validasi dan penerbitan surat pengantar kerja praktik.</flux:subheading>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <div class="lg:col-span-2 space-y-6">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari mahasiswa atau instansi..." icon="magnifying-glass" />
            
            <flux:tab.group>
                <flux:tabs wire:model.live="tab">
                    <flux:tab name="proses">Perlu Diproses</flux:tab>
                    <flux:tab name="disetujui">Disetujui (Antrian Cetak)</flux:tab>
                    <flux:tab name="riwayat">Riwayat</flux:tab>
                </flux:tabs>
        
                <flux:tab.panel name="proses">
                    <flux:card class="mt-4">
                        <flux:table :paginate="$this->perluDiproses">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>Instansi Tujuan</flux:table.column>
                                <flux:table.column>Tgl. Pengajuan</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse($this->perluDiproses as $surat)
                                    <flux:table.row :key="$surat->id">
                                        <flux:table.cell variant="strong">{{ $surat->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>{{ $surat->lokasi_surat_pengantar }}</flux:table.cell>
                                        <flux:table.cell>{{ \Carbon\Carbon::parse($surat->tanggal_pengajuan_surat_pengantar)->format('d/m/Y') }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button size="xs" variant="primary" wire:click="openProcessModal({{ $surat->id }})">Proses</flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row><flux:table.cell colspan="4" class="text-center py-12">Tidak ada pengajuan yang perlu diproses.</flux:table.cell></flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
        
                <flux:tab.panel name="disetujui">
                    <flux:card class="mt-4">
                        <flux:table :paginate="$this->disetujui">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>Nomor Surat</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse($this->disetujui as $surat)
                                    <flux:table.row :key="$surat->id">
                                        <flux:table.cell variant="strong">{{ $surat->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>{{ $surat->nomor_surat }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex items-center gap-2">
                                                <flux:button as="a" href="{{ route('surat.cetak', $surat->id) }}" target="_blank" size="xs" icon="document-arrow-down" variant="ghost">Ekspor</flux:button>
                                                <flux:button size="xs" variant="primary" wire:click="openPickupDateModal({{ $surat->id }})">Input Tgl. Ambil</flux:button>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row><flux:table.cell colspan="3" class="text-center py-12">Tidak ada surat dalam antrian cetak.</flux:table.cell></flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
        
                <flux:tab.panel name="riwayat">
                    <flux:card class="mt-4">
                        <flux:table :paginate="$this->riwayat">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>Instansi</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column>Tgl. Ambil</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse($this->riwayat as $surat)
                                    <flux:table.row :key="$surat->id">
                                        <flux:table.cell variant="strong">{{ $surat->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>{{ $surat->lokasi_surat_pengantar }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge :color="$surat->status_surat_pengantar === 'Siap Diambil' ? 'green' : 'red'" size="sm">{{ $surat->status_surat_pengantar }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $surat->tanggal_pengambilan_surat_pengantar ? \Carbon\Carbon::parse($surat->tanggal_pengambilan_surat_pengantar)->format('d/m/Y') : '-' }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row><flux:table.cell colspan="4" class="text-center py-12">Tidak ada riwayat.</flux:table.cell></flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
        
        <div class="lg:col-span-1 space-y-8">
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Alur Kerja Validasi</h3>
                <ol class="list-decimal list-inside space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>Periksa pengajuan baru di tab <b>"Perlu Diproses"</b>.</li>
                    <li>Klik <b>"Proses"</b>. Jika valid, isi nomor surat lalu klik <b>"Setujui & Pindahkan"</b>. Pengajuan akan pindah ke tab "Disetujui".</li>
                    <li>
                        <b>Jika ditolak:</b> Isi <b>Catatan Penolakan</b>, lalu klik "Tolak". Pengajuan akan langsung pindah ke "Riwayat".
                    </li>
                    <li>
                        Di tab <b>"Disetujui (Antrian Cetak)"</b>, ekspor surat untuk dicetak dan ditandatangani.
                    </li>
                    <li>
                        Setelah surat fisik siap, klik <b>"Input Tgl. Ambil"</b> untuk memberitahu mahasiswa dan memindahkan surat ke "Riwayat" dengan status "Siap Diambil".
                    </li>
                </ol>
            </flux:card>
        </div>
    </div>
    
    <flux:modal name="process-modal" class="md:w-[32rem]">
        @if ($suratToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Proses Pengajuan Surat</flux:heading>
                    <flux:text class="mt-2">Review detail, input nomor surat, lalu setujui atau tolak pengajuan.</flux:text>
                </div>
                <div class="space-y-2 rounded-lg border bg-neutral-50 p-3 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p><b>Mahasiswa:</b> {{ $suratToProcess->mahasiswa->nama_mahasiswa }}</p>
                    <p><b>Instansi:</b> {{ $suratToProcess->lokasi_surat_pengantar }}</p>
                    <p><b>Penerima:</b> {{ $suratToProcess->penerima_surat_pengantar }}</p>
                </div>
                <div>
                    <flux:input wire:model="nomor_surat" label="Nomor Surat" required />
                    {{-- @error('nomor_surat') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror --}}
                </div>
                <div>
                    <flux:textarea wire:model="rejectionNote" label="Catatan Penolakan (wajib diisi jika ditolak)" />
                    {{-- @error('rejectionNote') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror --}}
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="reject" variant="danger">Tolak</flux:button>
                    <flux:button wire:click="process" variant="primary">Setujui & Pindahkan</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="pickup-date-modal" class="md:w-96">
        @if ($suratToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Input Tanggal Pengambilan</flux:heading>
                    <flux:text class="mt-2">Konfirmasi bahwa surat untuk a/n <span class="font-bold">{{ $suratToProcess->mahasiswa->nama_mahasiswa }}</span> siap diambil.</flux:text>
                </div>
                <div>
                    <flux:input wire:model="tanggal_pengambilan" type="date" label="Tanggal Siap Diambil" required />
                    @error('tanggal_pengambilan') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="savePickupDate" variant="primary">Simpan & Beritahu Mahasiswa</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>