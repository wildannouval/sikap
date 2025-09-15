<?php

use App\Models\Ruangan;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\SeminarDijadwalkan;
use App\Notifications\JadwalSeminarDiubah;


new #[Title('Penjadwalan Seminar')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $tab = 'penjadwalan';

    // Properti BARU untuk Search, Filter, Sort
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Properti untuk Modal
    public ?Seminar $seminarToProcess = null;
    public string $tanggal_seminar = '';
    public ?int $ruangan_id = null;
    public string $jam_mulai = '';
    public string $jam_selesai = '';
    public string $rejectionNote = '';

    // Properti BARU untuk menampung jadwal yang bentrok
    public $jadwalTerisi = [];

    // Hook BARU untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'tab'])) {
            $this->resetPage();
        }
    }

    // Fungsi BARU untuk sorting
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // Base query BARU untuk menghindari duplikasi
    private function getBaseQuery()
    {
        return Seminar::with('kerjaPraktek.mahasiswa', 'ruangan')
            ->when($this->search, function ($query) {
                // Tambahkan pengelompokan where di sini
                $query->where(function ($subQuery) {
                    $subQuery->where('judul_kp_final', 'like', '%' . $this->search . '%')
                        ->orWhereHas('kerjaPraktek.mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%'));
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    #[Computed]
    public function perluDijadwalkan()
    {
        return $this->getBaseQuery()->where('status_seminar', 'Diajukan')->paginate(10, ['*'], 'jadwalPage');
    }

    #[Computed]
    public function riwayat()
    {
        return $this->getBaseQuery()
            ->whereIn('status_seminar', ['Dijadwalkan', 'Ditolak', 'Menunggu Konfirmasi'])
            ->when($this->statusFilter, fn($q) => $q->where('status_seminar', $this->statusFilter))
            ->paginate(10, ['*'], 'riwayatPage');
    }

    #[Computed]
    public function ruangans()
    {
        return Ruangan::orderBy('nama_ruangan')->get();
    }

    public function openProcessModal($id)
    {
        $this->seminarToProcess = Seminar::findOrFail($id);
        $this->tanggal_seminar = $this->seminarToProcess->tanggal_seminar;
        $this->ruangan_id = $this->seminarToProcess->ruangan_id;
        $this->jam_mulai = $this->seminarToProcess->jam_mulai;
        $this->jam_selesai = $this->seminarToProcess->jam_selesai;
        $this->reset('jadwalTerisi', 'rejectionNote');
        $this->resetErrorBag();
        Flux::modal('process-seminar-modal')->show();
    }
    
    public function rejectSeminar()
    {
        $validated = $this->validate([
            'rejectionNote' => 'required|string|min:10'],[
                'rejectionNote.required' => 'Catatan harus di isi!',
                'rejectionNote.min' => 'Catatan harus di isi minimal 10 karakter',
            ]);

        if($this->seminarToProcess){
            $this->seminarToProcess->update([
                'status_seminar' => 'Ditolak',
                'catatan' => $validated['rejectionNote'],
            ]);

            // Optional: Notify the student
            // $this->seminarToProcess->kerjaPraktek->mahasiswa->user->notify(new SeminarDitolak($this->seminarToProcess));
            
            Flux::modal('process-seminar-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pendaftaran seminar telah ditolak.');
        }
    }


    public function scheduleAndPublish()
    {
        $validated = $this->validate([
            'tanggal_seminar' => 'required|date',
            'ruangan_id' => 'required|exists:ruangans,id',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required|after:jam_mulai',
        ]);

        $isOccupied = Seminar::where('ruangan_id', $validated['ruangan_id'])
            ->where('tanggal_seminar', $validated['tanggal_seminar'])
            ->where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('jam_mulai', '<', $validated['jam_selesai'])
                        ->where('jam_selesai', '>', $validated['jam_mulai']);
                });
            })
            ->when($this->seminarToProcess, function ($query) {
                $query->where('id', '!=', $this->seminarToProcess->id);
            })
            ->exists();

        if ($isOccupied) {
            $this->addError('jadwal_bentrok', 'Ruangan dan waktu yang dipilih sudah terisi oleh seminar lain.');
            return;
        }

        if ($this->seminarToProcess) {
            $isJadwalDiubah = (
                $this->seminarToProcess->tanggal_seminar != $this->tanggal_seminar ||
                $this->seminarToProcess->ruangan_id != $this->ruangan_id ||
                $this->seminarToProcess->jam_mulai != $this->jam_mulai
            );

            $newStatus = $isJadwalDiubah ? 'Menunggu Konfirmasi' : 'Dijadwalkan';

            $this->seminarToProcess->update([
                'tanggal_seminar' => $this->tanggal_seminar,
                'ruangan_id' => $this->ruangan_id,
                'jam_mulai' => $this->jam_mulai,
                'jam_selesai' => $this->jam_selesai,
                'status_seminar' => $newStatus
            ]);
            
            $notification = $isJadwalDiubah 
                ? new JadwalSeminarDiubah($this->seminarToProcess) 
                : new SeminarDijadwalkan($this->seminarToProcess);

            $this->seminarToProcess->kerjaPraktek->mahasiswa->user->notify($notification);

            Flux::modal('process-seminar-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Seminar telah dijadwalkan dan mahasiswa telah diberitahu.');
        }
    }

    public function updatedTanggalSeminar($value)
    {
        $this->jadwalTerisi = Seminar::with('ruangan')
            ->where('status_seminar', 'Dijadwalkan')
            ->where('tanggal_seminar', $value)
            ->orderBy('jam_mulai')
            ->get();
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Penjadwalan Seminar</flux:heading>
        <flux:subheading size="lg">Validasi pendaftaran dan jadwalkan seminar mahasiswa.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari mahasiswa atau judul..." icon="magnifying-glass" />
                </div>
                @if ($tab === 'riwayat')
                    <div class="w-full md:w-64">
                        <flux:select wire:model.live="statusFilter">
                            <option value="">Semua Status Riwayat</option>
                            <option value="Dijadwalkan">Dijadwalkan</option>
                            <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                            <option value="Ditolak">Ditolak</option>
                        </flux:select>
                    </div>
                @endif
            </div>

            <flux:tab.group>
                <flux:tabs wire:model.live="tab">
                    <flux:tab name="penjadwalan">Perlu Dijadwalkan</flux:tab>
                    <flux:tab name="riwayat">Riwayat</flux:tab>
                </flux:tabs>
        
                <flux:tab.panel name="penjadwalan">
                    <flux:card class="mt-4">
                        <flux:table :paginate="$this->perluDijadwalkan">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>Judul KP</flux:table.column>
                                <flux:table.column>Tgl. Pendaftaran</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($this->perluDijadwalkan as $seminar)
                                    <flux:table.row :key="$seminar->id">
                                        <flux:table.cell variant="strong">{{ $seminar->kerjaPraktek->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>{{ Str::limit($seminar->judul_kp_final, 40) }}</flux:table.cell>
                                        <flux:table.cell>{{ \Carbon\Carbon::parse($seminar->created_at)->format('d/m/Y') }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button size="xs" variant="primary" wire:click="openProcessModal({{ $seminar->id }})">Proses</flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                            Tidak ada pendaftaran seminar yang perlu diproses.
                                        </flux:table.cell>
                                    </flux:table.row>
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
                                <flux:table.column>Jadwal Final</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($this->riwayat as $seminar)
                                    <flux:table.row :key="'riwayat-' . $seminar->id">
                                        <flux:table.cell variant="strong">{{ $seminar->kerjaPraktek->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                        <flux:table.cell>
                                            @if($seminar->tanggal_seminar)
                                                {{ \Carbon\Carbon::parse($seminar->tanggal_seminar)->format('d/m/Y') }}, {{ \Carbon\Carbon::parse($seminar->jam_mulai)->format('H:i') }}
                                            @else
                                                -
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @php
                                                $color = match($seminar->status_seminar) {
                                                    'Dijadwalkan' => 'blue',
                                                    'Menunggu Konfirmasi' => 'orange',
                                                    'Ditolak' => 'red',
                                                    default => 'zinc',
                                                };
                                            @endphp
                                            <flux:badge :color="$color" size="sm">{{ $seminar->status_seminar }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($seminar->status_seminar === 'Dijadwalkan')
                                                <flux:button as="a" href="{{ route('bap.cetak', $seminar->id) }}" size="xs" variant="ghost" icon="document-arrow-down" />
                                            @else
                                                -
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="4" class="text-center py-12">Belum ada riwayat penjadwalan.</flux:table.cell>
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
                <h3 class="text-lg font-semibold mb-4">Alur Kerja Penjadwalan</h3>
                <ol class="list-decimal list-inside space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>
                        <b>Proses Pendaftaran:</b><br>
                        Periksa pendaftaran baru di tab "Perlu Dijadwalkan". Klik "Proses" untuk membuka detail.
                    </li>
                    <li>
                        <b>Penjadwalan:</b><br>
                        Tentukan tanggal, jam, dan ruangan seminar. Anda bisa menyetujui usulan mahasiswa atau menetapkan jadwal baru. "Asisten Jadwal" akan muncul jika ada potensi bentrok.
                    </li>
                    <li>
                        <b>Konfirmasi Mahasiswa:</b><br>
                        Jika Anda mengubah jadwal, status akan menjadi "Menunggu Konfirmasi". Mahasiswa harus menyetujui jadwal baru tersebut dari sisi mereka.
                    </li>
                     <li>
                        <b>Finalisasi & Berita Acara:</b><br>
                        Setelah jadwal disetujui (baik langsung atau via konfirmasi), status menjadi "Dijadwalkan". Anda dapat mengunduh Berita Acara (BAP) dari tab "Riwayat".
                    </li>
                </ol>
            </flux:card>
        </div>
    </div>
    
    <flux:modal name="process-seminar-modal" class="md:w-[32rem]">
        @if ($seminarToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Jadwalkan Seminar</flux:heading>
                    <flux:text class="mt-2">Setujui dan tetapkan jadwal final untuk seminar.</flux:text>
                </div>

                <div class="space-y-2 rounded-lg border bg-neutral-50 p-3 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p><b>Usulan Mahasiswa:</b></p>
                    <p>
                        Tgl: {{ \Carbon\Carbon::parse($seminarToProcess->tanggal_seminar)->format('d F Y') }},
                        Jam: {{ \Carbon\Carbon::parse($seminarToProcess->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($seminarToProcess->jam_selesai)->format('H:i') }},
                        Ruang: {{ $seminarToProcess->ruangan->nama_ruangan }}
                    </p>
                    <flux:button as="a" href="{{ asset('storage/' . $seminarToProcess->berkas_laporan_final) }}" target="_blank" size="xs" icon="document-arrow-down">Unduh Laporan Final</flux:button>
                </div>

                <div class="space-y-4">
                    <flux:input wire:model.live="tanggal_seminar" type="date" label="Tanggal Seminar Final" required />

                    @if(count($jadwalTerisi) > 0)
                        <flux:callout type="info" class="text-sm">
                            <p class="font-bold">Jadwal Terisi pada {{ \Carbon\Carbon::parse($tanggal_seminar)->format('d F Y') }}:</p>
                            <ul class="list-disc pl-5 mt-1">
                                @foreach($jadwalTerisi as $jadwal)
                                    <li>{{ $jadwal->ruangan->nama_ruangan }} ({{ \Carbon\Carbon::parse($jadwal->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($jadwal->jam_selesai)->format('H:i') }})</li>
                                @endforeach
                            </ul>
                        </flux:callout>
                    @endif

                    <flux:select wire:model="ruangan_id" label="Ruangan Final" required>
                        <option value="">Pilih Ruangan</option>
                        @foreach($this->ruangans as $ruangan)
                            <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }}</option>
                        @endforeach
                    </flux:select>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="jam_mulai" type="time" label="Jam Mulai Final" required />
                        <flux:input wire:model="jam_selesai" type="time" label="Jam Selesai Final" required />
                    </div>
                </div>

                @if ($errors->has('jadwal_bentrok'))
                    <flux:callout variant="danger" icon="x-circle">
                        <p>{{ $errors->first('jadwal_bentrok') }}</p>
                    </flux:callout>
                @endif
                
                <div class="pt-4 border-t dark:border-zinc-700">
                     {{-- [FIX] Tambahkan wire:model.live agar tombol Tolak langsung aktif --}}
                     <flux:textarea wire:model.live="rejectionNote" label="Catatan Penolakan (opsional, isi jika ingin menolak)" placeholder="Contoh: Berkas laporan perlu direvisi sesuai arahan dosen..." />
                </div>

                <div class="flex justify-between items-center">
                    <flux:button wire:click="rejectSeminar" variant="danger">Tolak Pendaftaran</flux:button>
                    <div class="flex gap-3">
                        <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                        <flux:button wire:click="scheduleAndPublish" variant="primary">Jadwalkan Seminar</flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>