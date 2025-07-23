<?php

use App\Models\KerjaPraktek;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';
    public ?Seminar $seminarToGrade = null;
    public $berita_acara_signed;

// Properti untuk komponen nilai
    public ?float $nilai_lapangan = null;
    public ?float $nilai_dosen = null;
    public float $nilai_angka_final = 0;
    public string $nilai_akhir = '';

    // Hook yang berjalan setiap kali nilai lapangan atau nilai dosen berubah
    public function updated($property)
    {
        if (in_array($property, ['nilai_lapangan', 'nilai_dosen'])) {
            $this->calculateFinalGrade();
        }
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    // Fungsi BARU untuk menghitung nilai akhir secara otomatis
    public function calculateFinalGrade()
    {
        $nilaiLapangan = floatval($this->nilai_lapangan);
        $nilaiDosen = floatval($this->nilai_dosen);

        if (is_numeric($this->nilai_lapangan) && is_numeric($this->nilai_dosen)) {
            // Hitung nilai angka final sesuai bobot
            $this->nilai_angka_final = ($nilaiLapangan * 0.4) + ($nilaiDosen * 0.6);

            // Konversi ke nilai huruf
            $nilaiAngka = $this->nilai_angka_final;
            if ($nilaiAngka >= 85) $this->nilai_akhir = 'A';
            elseif ($nilaiAngka >= 80) $this->nilai_akhir = 'A-';
            elseif ($nilaiAngka >= 75) $this->nilai_akhir = 'B+';
            elseif ($nilaiAngka >= 70) $this->nilai_akhir = 'B';
            elseif ($nilaiAngka >= 65) $this->nilai_akhir = 'B-';
            elseif ($nilaiAngka >= 60) $this->nilai_akhir = 'C+';
            elseif ($nilaiAngka >= 55) $this->nilai_akhir = 'C';
            elseif ($nilaiAngka >= 40) $this->nilai_akhir = 'D';
            else $this->nilai_akhir = 'E';
        } else {
            $this->nilai_angka_final = 0;
            $this->nilai_akhir = '';
        }
    }

    public function openGradeModal($seminarId)
    {
        $this->seminarToGrade = Seminar::with('kerjaPraktek.mahasiswa')->findOrFail($seminarId);
        $this->nilai_lapangan = $this->seminarToGrade->nilai_pembimbing_lapangan;
        $this->nilai_dosen = $this->seminarToGrade->nilai_dosen_pembimbing;
        $this->calculateFinalGrade(); // Langsung hitung nilai saat modal dibuka
        $this->reset('berita_acara_signed');
        $this->resetErrorBag();
        Flux::modal('grade-modal')->show();
    }

    public function saveGrade()
    {
        $validated = $this->validate([
            'nilai_lapangan' => 'required|numeric|min:0|max:100',
            'nilai_dosen' => 'required|numeric|min:0|max:100',
        ]);

        // ... (Logika validasi & penyimpanan file tidak berubah)

        if ($this->seminarToGrade) {
            $updateData = [
                'status_seminar' => 'Dinilai',
                'nilai_pembimbing_lapangan' => $this->nilai_lapangan,
                'nilai_dosen_pembimbing' => $this->nilai_dosen,
                'nilai_akhir' => $this->nilai_akhir, // Simpan nilai huruf yang sudah dihitung
            ];
            // ... (Logika penyimpanan file tidak berubah)
            $this->seminarToGrade->update($updateData);
            Flux::modal('grade-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Penilaian seminar telah diperbarui.');
        }
    }

    // Hook BARU untuk reset paginasi
    public function updatedSearch()
    {
        $this->resetPage();
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
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) {
            return KerjaPraktek::where('id', -1); // Kembalikan query kosong
        }

        return KerjaPraktek::with('mahasiswa', 'seminar')
            ->where('dosen_pembimbing_id', $dosenId)
            ->when($this->search, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%'))
                    ->orWhere('judul_kp', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

    /**
     * Data untuk tab "Perlu Dinilai".
     * Mengambil data KP yang seminarnya sudah Dijadwalkan.
     */
    #[Computed]
    public function perluDinilai()
    {
        return $this->getBaseQuery()
            ->whereHas('seminar', fn($q) => $q->where('status_seminar', 'Dijadwalkan'))
            ->paginate(10, ['*'], 'nilaiPage');
    }

    #[Computed]
    public function riwayatPenilaian()
    {
        return $this->getBaseQuery()
            ->whereHas('seminar', fn($q) => $q->where('status_seminar', 'Dinilai'))
            ->paginate(10, ['*'], 'riwayatPage');
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <flux:heading size="xl" level="1">Penilaian Kerja Praktik</flux:heading>
    <flux:subheading size="lg" class="mb-6">Upload berita acara dan input nilai akhir seminar mahasiswa.</flux:subheading>
    <flux:separator variant="subtle"/>

    {{-- Input Search BARU --}}
    <div class="mt-6 flex">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari berdasarkan nama mahasiswa atau judul KP..." icon="magnifying-glass" />
        </div>
    </div>

    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="penilaian">Perlu Dinilai</flux:tab>
            <flux:tab name="riwayat">Riwayat Penilaian</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="penilaian">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->perluDinilai">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('seminars.tanggal_seminar')">Tgl. Seminar</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->perluDinilai as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->seminar->judul_kp_final, 40) }}</flux:table.cell>
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
            </flux:card>
        </flux:tab.panel>

        <flux:tab.panel name="riwayat">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->riwayatPenilaian">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Nilai Akhir</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayatPenilaian as $kp)
                            <flux:table.row :key="'riwayat-' . $kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->seminar->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell>
                                    {{-- PERBAIKAN: Ganti nilai_seminar menjadi nilai_akhir --}}
                                    <flux:badge color="green" size="sm">{{ $kp->seminar->nilai_akhir }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" wire:click="openGradeModal({{ $kp->seminar->id }})">
                                        Edit Nilai
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4" class="text-center">Belum ada riwayat penilaian.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Modal untuk Memberi Nilai (DIPERBARUI) --}}
    <flux:modal name="grade-modal" class="md:w-96">
        @if ($seminarToGrade)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $seminarToGrade->nilai_akhir ? 'Edit' : 'Input' }} Nilai Seminar</flux:heading>
                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <p>Mahasiswa: <span class="font-bold">{{ $seminarToGrade->kerjaPraktek->mahasiswa->nama_mahasiswa }}</span></p>
                    </div>
                </div>
                <div class="space-y-4">
                    @if($seminarToGrade->berita_acara_signed)
                        <div class="text-sm">
                            <flux:label>Berita Acara Saat Ini</flux:label>
                            <flux:button as="a" href="{{ asset('storage/' . $seminarToGrade->berita_acara_signed) }}" target="_blank" variant="ghost" size="sm" icon="document-text" class="!text-indigo-600 !p-0 hover:underline">
                                Lihat File
                            </flux:button>
                        </div>
                    @endif

                        <hr class="dark:border-neutral-700">

                        {{-- Input Nilai Komponen --}}
                        <flux:input wire:model.live="nilai_lapangan" type="number" step="0.01" label="Nilai Pemb. Lapangan (40%)" placeholder="0-100" required />
                        @error('nilai_lapangan') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

                        <flux:input wire:model.live="nilai_dosen" type="number" step="0.01" label="Nilai Dosen Pemb. (60%)" placeholder="0-100" required />
                        @error('nilai_dosen') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

                        <hr class="dark:border-neutral-700">

                        {{-- Tampilan Nilai Akhir (Read-only) --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:label>Nilai Angka Final</flux:label>
                                <div class="mt-1 flex h-10 w-full items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                    {{ number_format($nilai_angka_final, 2) }}
                                </div>
                            </div>
                            <div>
                                <flux:label>Nilai Huruf Final</flux:label>
                                <div class="mt-1 flex h-10 w-full items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-2xl font-bold dark:border-zinc-700 dark:bg-zinc-800">
                                    {{ $nilai_akhir ?: '-' }}
                                </div>
                            </div>
                        </div>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="saveGrade" variant="primary">Simpan Nilai</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
