<?php

use App\Models\Ruangan;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Penjadwalan Seminar')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $tab = 'penjadwalan';

    // Properti untuk Modal
    public ?Seminar $seminarToProcess = null;
    public string $tanggal_seminar = '';
    public ?int $ruangan_id = null;
    public string $jam_mulai = '';
    public string $jam_selesai = '';
    public string $tanggal_pengambilan_berita_acara = '';

    #[Computed]
    public function perluDijadwalkan()
    {
        return Seminar::with('kerjaPraktek.mahasiswa')
            ->where('status_seminar', 'Diajukan')
            ->latest()->paginate(10, ['*'], 'jadwalPage');
    }
    #[Computed]
    public function riwayat()
    {
        return Seminar::with('kerjaPraktek.mahasiswa')
            ->whereIn('status_seminar', ['Dijadwalkan', 'Ditolak'])
            ->latest()->paginate(10, ['*'], 'riwayatPage');
    }
    #[Computed]
    public function ruangans()
    {
        return Ruangan::orderBy('nama_ruangan')->get();
    }

    public function openProcessModal($id)
    {
        $this->seminarToProcess = Seminar::findOrFail($id);
        // Isi form dengan usulan dari mahasiswa
        $this->tanggal_seminar = $this->seminarToProcess->tanggal_seminar;
        $this->ruangan_id = $this->seminarToProcess->ruangan_id;
        $this->jam_mulai = $this->seminarToProcess->jam_mulai;
        $this->jam_selesai = $this->seminarToProcess->jam_selesai;
        $this->reset('tanggal_pengambilan_berita_acara');
        Flux::modal('process-seminar-modal')->show();
    }

    public function scheduleAndPublish()
    {
        $validated = $this->validate([
            'tanggal_seminar' => 'required|date',
            'ruangan_id' => 'required|exists:ruangans,id',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'tanggal_pengambilan_berita_acara' => 'required|date',
        ]);

        if ($this->seminarToProcess) {
            $this->seminarToProcess->update(array_merge($validated, [
                'status_seminar' => 'Dijadwalkan'
            ]));

            Flux::modal('process-seminar-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Seminar telah dijadwalkan.');
            $this->dispatch('seminar-scheduled-and-publish', id: $this->seminarToProcess->id);
        }
    }
}; ?>

<div x-on:seminar-scheduled-and-publish.window="window.open('/seminar/' + $event.detail.id + '/export-berita-acara', '_blank')">
    <flux:heading size="xl" level="1">Penjadwalan Seminar</flux:heading>
    <flux:subheading size="lg" class="mb-6">Validasi pendaftaran dan jadwalkan seminar mahasiswa.</flux:subheading>
    <flux:separator variant="subtle"/>

    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="penjadwalan">Perlu Dijadwalkan</flux:tab>
            <flux:tab name="riwayat">Riwayat</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="penjadwalan">
            <flux:card class="mt-4">
                <flux:table>
                    {{-- Definisi kolom untuk tabel "Perlu Dijadwalkan" --}}
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
                                    <flux:button size="xs" variant="primary" wire:click="openProcessModal({{ $seminar->id }})">
                                        Proses
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center">Tidak ada pendaftaran seminar yang perlu diproses.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">{{ $this->perluDijadwalkan->links() }}</div>
            </flux:card>
        </flux:tab.panel>

        <flux:tab.panel name="riwayat">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Jadwal Final</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        {{-- KOLOM BARU --}}
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayat as $seminar)
                            <flux:table.row :key="'riwayat-' . $seminar->id">
                                <flux:table.cell variant="strong">{{ $seminar->kerjaPraktek->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($seminar->judul_kp_final, 40) }}</flux:table.cell>
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
                                            'Ditolak' => 'red',
                                            'SPK Terbit' => 'emerald', // Ini dari modul sebelumnya, mungkin statusnya tidak akan muncul di sini
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge :color="$color" size="sm">{{ $seminar->status_seminar }}</flux:badge>
                                </flux:table.cell>
                                {{-- DATA BARU --}}
                                <flux:table.cell>
                                    @if ($seminar->status_seminar === 'Dijadwalkan')
                                        <flux:button
                                            as="a"
                                            href="{{ route('seminar.export-berita-acara', $seminar->id) }}"
                                            target="_blank"
                                            size="xs"
                                            variant="ghost"
                                            icon="arrow-down-tray">
                                            Ekspor Ulang
                                        </flux:button>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center">Belum ada riwayat penjadwalan.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">{{ $this->riwayat->links() }}</div>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:modal name="process-seminar-modal" class="md:w-[32rem]">
        @if ($seminarToProcess)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Jadwalkan Seminar</flux:heading>
                    <flux:text class="mt-2">Setujui dan tetapkan jadwal final untuk seminar.</flux:text>
                </div>
                {{-- Form Penjadwalan --}}
                <div class="space-y-4">
                    <flux:input wire:model="tanggal_seminar" type="date" label="Tanggal Seminar Final" required />
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
                    <hr class="dark:border-neutral-700">
                    <flux:input wire:model="tanggal_pengambilan_berita_acara" type="date" label="Tanggal Pengambilan Berita Acara" required />
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="scheduleAndPublish" variant="primary">Jadwalkan & Terbitkan Berita Acara</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
