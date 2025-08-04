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
            ->latest();
    }

    #[Computed]
    public function perluDiproses()
    {
        return $this->getBaseQuery()->where('status_surat_pengantar', 'Diajukan')->paginate(10, ['*'], 'prosesPage');
    }

    #[Computed]
    public function siapDiambil()
    {
        return $this->getBaseQuery()->where('status_surat_pengantar', 'Siap Diambil')->paginate(10, ['*'], 'siapPage');
    }

    #[Computed]
    public function riwayat()
    {
        return $this->getBaseQuery()->whereIn('status_surat_pengantar', ['Disetujui', 'Ditolak'])->paginate(10, ['*'], 'riwayatPage');
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
        $this->resetErrorBag();
        Flux::modal('pickup-date-modal')->show();
    }

    public function process()
    {
        $this->validate(['nomor_surat' => 'required|string|max:255'], ['nomor_surat.required' => 'Nomor surat wajib diisi.']);
        if ($this->suratToProcess) {
            $this->suratToProcess->update([
                'status_surat_pengantar' => 'Siap Diambil',
                'nomor_surat' => $this->nomor_surat,
                'tanggal_disetujui_surat_pengantar' => now(),
            ]);
            Flux::modal('process-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Surat telah divalidasi dan dipindahkan ke antrian Siap Diambil.');
            // Hapus redirect download
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
            $this->suratToProcess->update([
                'status_surat_pengantar' => 'Disetujui',
                'tanggal_pengambilan_surat_pengantar' => $this->tanggal_pengambilan,
            ]);
            $this->suratToProcess->mahasiswa->user->notify(new SuratPengantarDisetujui($this->suratToProcess));
            Flux::modal('pickup-date-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Tanggal pengambilan berhasil disimpan.');
        }
    }
}; ?>

<div>
    <flux:heading size="xl" level="1">Validasi Surat Pengantar</flux:heading>
    <flux:subheading size="lg" class="mb-6">Kelola alur validasi dan penerbitan surat pengantar.</flux:subheading>
    <flux:separator variant="subtle"/>

    <div class="mt-6"><flux:input wire:model.live.debounce.300ms="search" placeholder="Cari mahasiswa atau instansi..." icon="magnifying-glass" /></div>

    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="proses">Perlu Diproses</flux:tab>
            <flux:tab name="siap">Siap Diambil</flux:tab>
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
                            <flux:table.row><flux:table.cell colspan="4" class="text-center">Tidak ada pengajuan yang perlu diproses.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>

        {{-- Ganti seluruh isi panel tab "siap" dengan ini --}}
        <flux:tab.panel name="siap">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->siapDiambil">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Instansi Tujuan</flux:table.column>
                        <flux:table.column>Nomor Surat</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->siapDiambil as $surat)
                            <flux:table.row :key="$surat->id">
                                <flux:table.cell variant="strong">{{ $surat->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ $surat->lokasi_surat_pengantar }}</flux:table.cell>
                                <flux:table.cell>{{ $surat->nomor_surat }}</flux:table.cell>
                                <flux:table.cell>
                                    {{-- PERBAIKAN: Tambahkan tombol Ekspor di sini --}}
                                    <div class="flex items-center gap-2">
                                        <flux:button
                                            as="a"
                                            href="{{ route('surat-pengantar.export', $surat->id) }}"
                                            target="_blank"
                                            size="xs"
                                            icon="document-arrow-down"
                                            variant="ghost">
                                            Ekspor
                                        </flux:button>
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            wire:click="openPickupDateModal({{ $surat->id }})">
                                            Input Tgl. Pengambilan
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center">Tidak ada surat yang siap diambil.</flux:table.cell></flux:table.row>
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
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->riwayat as $surat)
                            <flux:table.row :key="$surat->id">
                                <flux:table.cell variant="strong">{{ $surat->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ $surat->lokasi_surat_pengantar }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$surat->status_surat_pengantar === 'Disetujui' ? 'green' : 'red'" size="sm">
                                        {{ $surat->status_surat_pengantar }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($surat->status_surat_pengantar === 'Disetujui' || $surat->status_surat_pengantar === 'Siap Diambil')
                                        <flux:button as="a" href="{{ route('surat-pengantar.export', $surat->id) }}" target="_blank" size="xs" icon="document-arrow-down" variant="ghost">
                                            Ekspor Surat
                                        </flux:button>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center">Tidak ada riwayat.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Modal untuk Proses --}}
    <flux:modal name="process-modal" class="md:w-[32rem]">
        @if ($suratToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Proses Pengajuan Surat</flux:heading>
                    <flux:text class="mt-2">Review detail, input nomor surat, lalu validasi atau tolak pengajuan.</flux:text>
                </div>
                <div class="space-y-2 rounded-lg border bg-neutral-50 p-3 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p><b>Mahasiswa:</b> {{ $suratToProcess->mahasiswa->nama_mahasiswa }}</p>
                    <p><b>Instansi:</b> {{ $suratToProcess->lokasi_surat_pengantar }}</p>
                    <p><b>Penerima:</b> {{ $suratToProcess->penerima_surat_pengantar }}</p>
                </div>
                <div>
                    <flux:input wire:model="nomor_surat" label="Nomor Surat" required />
                    @error('nomor_surat') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <flux:textarea wire:model="rejectionNote" label="Catatan Penolakan (wajib diisi jika ditolak)" />
                    @error('rejectionNote') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="reject" variant="danger">Tolak</flux:button>
                    <flux:button wire:click="process" variant="primary">Validasi & Pindahkan</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal untuk Input Tanggal Pengambilan --}}
    <flux:modal name="pickup-date-modal" class="md:w-96">
        @if ($suratToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Input Tanggal Pengambilan</flux:heading>
                    <flux:text class="mt-2">Untuk surat a/n <span class="font-bold">{{ $suratToProcess->mahasiswa->nama_mahasiswa }}</span></flux:text>
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
