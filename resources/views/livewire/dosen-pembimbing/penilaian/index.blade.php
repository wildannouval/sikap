<?php

use App\Models\KerjaPraktek;
use App\Models\Seminar;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    // Properti BARU untuk Search & Sort
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Properti untuk Modal Penilaian
    public ?Seminar $seminarToGrade = null;
    public string $nilai_seminar = '';
    public $berita_acara_signed;

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

    public function openGradeModal($seminarId)
    {
        $this->seminarToGrade = Seminar::with('kerjaPraktek.mahasiswa')->findOrFail($seminarId);
        $this->nilai_seminar = $this->seminarToGrade->nilai_seminar ?? '';
        $this->reset('berita_acara_signed');
        $this->resetErrorBag();
        Flux::modal('grade-modal')->show();
    }

    public function saveGrade()
    {
        // Aturan validasi dasar
        $rules = [
            'nilai_seminar' => 'required|string|max:5',
        ];

        // Jadikan upload file wajib hanya jika belum pernah diunggah sebelumnya
        if (!$this->seminarToGrade->berita_acara_signed) {
            $rules['berita_acara_signed'] = 'required|file|mimes:pdf|max:2048';
        } elseif ($this->berita_acara_signed) {
            // Jika ada file baru yang diunggah saat edit, validasi file tersebut
            $rules['berita_acara_signed'] = 'file|mimes:pdf|max:2048';
        }

        $validated = $this->validate($rules);

        if ($this->seminarToGrade) {
            $updateData = [
                'status_seminar' => 'Dinilai',
                'nilai_seminar' => $validated['nilai_seminar'],
            ];

            // Cek apakah ada file baru yang diunggah
            if ($this->berita_acara_signed) {
                // Hapus file lama jika ada
                if ($this->seminarToGrade->berita_acara_signed) {
                    Storage::disk('public')->delete($this->seminarToGrade->berita_acara_signed);
                }
                // Simpan file baru dan tambahkan path ke data update
                $updateData['berita_acara_signed'] = $this->berita_acara_signed->store('berita-acara-signed', 'public');
            }

            $this->seminarToGrade->update($updateData);

            Flux::modal('grade-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Penilaian seminar telah diperbarui.');
        }
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
                        {{-- KOLOM BARU --}}
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayatPenilaian as $kp)
                            <flux:table.row :key="'riwayat-' . $kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->seminar->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell><flux:badge color="green" size="sm">{{ $kp->seminar->nilai_seminar }}</flux:badge></flux:table.cell>
                                {{-- DATA BARU --}}
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
                    {{-- Judul dinamis --}}
                    <flux:heading size="lg">{{ $seminarToGrade->nilai_seminar ? 'Edit' : 'Input' }} Nilai Seminar</flux:heading>
                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <p>Mahasiswa: <span class="font-bold">{{ $seminarToGrade->kerjaPraktek->mahasiswa->nama_mahasiswa }}</span></p>
                    </div>
                </div>
                <div class="space-y-4">
                    {{-- Menampilkan file yang sudah ada --}}
                    @if($seminarToGrade->berita_acara_signed)
                        <div class="text-sm">
                            <flux:label>Berita Acara Saat Ini</flux:label>
                            <flux:button as="a" href="{{ asset('storage/' . $seminarToGrade->berita_acara_signed) }}" target="_blank" variant="ghost" size="sm" icon="document-text" class="!text-indigo-600 !p-0 hover:underline">
                                Lihat File
                            </flux:button>
                        </div>
                    @endif

                    <flux:input wire:model="berita_acara_signed" type="file" :label="$seminarToGrade->berita_acara_signed ? 'Upload untuk Mengganti (Opsional)' : 'Upload Berita Acara (TTD)'" helper="File PDF, maks 2MB." :required="!$seminarToGrade->berita_acara_signed" />
                    @error('berita_acara_signed') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

                    <flux:input wire:model="nilai_seminar" label="Nilai Akhir" placeholder="Contoh: A, B+, C" required />
                    @error('nilai_seminar') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="saveGrade" variant="primary">Simpan Perubahan</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
