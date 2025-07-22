<?php

use App\Models\KerjaPraktek;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new #[Title('Penilaian KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public string $tab = 'penilaian';

    // Properti untuk Modal Penilaian
    public ?Seminar $seminarToGrade = null;
    public string $nilai_seminar = '';
    public $berita_acara_signed;

    /**
     * Data untuk tab "Perlu Dinilai".
     * Mengambil data KP yang seminarnya sudah Dijadwalkan.
     */
    #[Computed]
    public function perluDinilai()
    {
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) { return KerjaPraktek::where('id', -1)->paginate(10, ['*'], 'nilaiPage'); }

        return KerjaPraktek::with('mahasiswa', 'seminar')
            ->where('dosen_pembimbing_id', $dosenId)
            ->whereHas('seminar', function ($query) {
                $query->where('status_seminar', 'Dijadwalkan');
            })
            ->latest()
            ->paginate(10, ['*'], 'nilaiPage');
    }

    /**
     * Data untuk tab "Riwayat Penilaian".
     */
    #[Computed]
    public function riwayatPenilaian()
    {
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) { return KerjaPraktek::where('id', -1)->paginate(10, ['*'], 'riwayatPage'); }

        return KerjaPraktek::with('mahasiswa', 'seminar')
            ->where('dosen_pembimbing_id', $dosenId)
            ->whereHas('seminar', function ($query) {
                $query->where('status_seminar', 'Dinilai');
            })
            ->latest()
            ->paginate(10, ['*'], 'riwayatPage');
    }

    /**
     * Membuka modal untuk memberi nilai.
     */
    public function openGradeModal($seminarId)
    {
        $this->seminarToGrade = Seminar::findOrFail($seminarId);
        $this->reset('nilai_seminar', 'berita_acara_signed');
        $this->resetErrorBag();
        Flux::modal('grade-modal')->show();
    }

    /**
     * Menyimpan nilai dan berkas berita acara.
     */
    public function saveGrade()
    {
        $validated = $this->validate([
            'nilai_seminar' => 'required|string|max:5',
            'berita_acara_signed' => 'required|file|mimes:pdf|max:2048', // PDF, maks 2MB
        ]);

        if ($this->seminarToGrade) {
            // Hapus file berita acara lama jika ada (saat edit ulang)
            if ($this->seminarToGrade->berita_acara_signed) {
                Storage::disk('public')->delete($this->seminarToGrade->berita_acara_signed);
            }

            // Simpan file baru
            $filePath = $this->berita_acara_signed->store('berita-acara-signed', 'public');

            // Update data seminar
            $this->seminarToGrade->update([
                'status_seminar' => 'Dinilai',
                'nilai_seminar' => $validated['nilai_seminar'],
                'berita_acara_signed' => $filePath,
            ]);

            Flux::modal('grade-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Penilaian seminar telah disimpan.');
        }
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <flux:heading size="xl" level="1">Penilaian Kerja Praktik</flux:heading>
    <flux:subheading size="lg" class="mb-6">Upload berita acara dan input nilai akhir seminar mahasiswa.</flux:subheading>
    <flux:separator variant="subtle"/>

    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="penilaian">Perlu Dinilai</flux:tab>
            <flux:tab name="riwayat">Riwayat Penilaian</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="penilaian">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Tgl. Seminar</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->perluDinilai as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($kp->seminar->tanggal_seminar)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="primary" wire:click="openGradeModal({{ $kp->seminar->id }})">
                                        Beri Penilaian
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center">Tidak ada seminar yang perlu dinilai.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">{{ $this->perluDinilai->links() }}</div>
            </flux:card>
        </flux:tab.panel>

        <flux:tab.panel name="riwayat">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Nilai Akhir</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayatPenilaian as $kp)
                            <flux:table.row :key="'riwayat-' . $kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell><flux:badge color="green" size="sm">{{ $kp->seminar->nilai_seminar }}</flux:badge></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="3" class="text-center">Belum ada riwayat penilaian.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">{{ $this->riwayatPenilaian->links() }}</div>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Modal untuk Memberi Nilai --}}
    <flux:modal name="grade-modal" class="md:w-96">
        @if ($seminarToGrade)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Input Nilai Seminar</flux:heading>
                    <flux:text class="mt-2">
                        Untuk mahasiswa: <span class="font-bold">{{ $seminarToGrade->kerjaPraktek->mahasiswa->nama_mahasiswa }}</span>
                    </flux:text>
                </div>
                <div class="space-y-4">
                    <flux:input wire:model="berita_acara_signed" type="file" label="Upload Berita Acara (TTD)" helper="File PDF, maks 2MB." required />
                    <flux:input wire:model="nilai_seminar" label="Nilai Akhir" placeholder="Contoh: A, B+, C" required />
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="saveGrade" variant="primary">Simpan Nilai</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
