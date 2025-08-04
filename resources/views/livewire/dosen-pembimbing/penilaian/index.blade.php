<?php

use App\Models\KerjaPraktek;
use App\Models\Seminar;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Penilaian;

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

    // Properti untuk Modal Penilaian
    public ?Seminar $seminarToGrade = null;
    public $berita_acara_signed;

    // Properti BARU untuk komponen nilai
    public array $nilaiLapangan = [];
    public array $nilaiPembimbing = [];
    public float $nilaiAngkaFinal = 0;
    public string $nilaiHurufFinal = '';

    // Properti untuk komponen nilai
    //    public ?float $nilai_lapangan = null;
    //    public ?float $nilai_dosen = null;
    //    public float $nilai_angka_final = 0;
    //    public string $nilai_akhir = '';

    // Daftar komponen penilaian
    public array $komponenLapangan = [
        'kesesuaian' => 'Kesesuaian dengan rencana kerja',
        'kehadiran' => 'Kehadiran di lokasi Kerja praktek',
        'kedisiplinan' => 'Kedisiplinan sikap etika dan tingkah laku',
        'keaktifan' => 'Keaktifan dan kreatifitas',
        'kecermatan' => 'Kecermatan',
        'tanggung_jawab' => 'Tanggung jawab',
    ];

    public array $komponenPembimbing = [
        'sistematika_laporan' => 'Sistematika penulisan laporan',
        'tata_bahasa' => 'Tata Bahasa penulisan laporan',
        'sistematika_seminar' => 'Sistematika penjelasan materi seminar',
        'kecocokan_isi' => 'Kecocokan isi laporan dengan materi seminar',
        'materi_kp' => 'Materi Kerja Praktek',
        'penguasaan_masalah' => 'Penguasaan terhadap permasalahan tugas akhir',
        'diskusi' => 'Diskusi',
    ];

    // Hook yang berjalan setiap kali ada perubahan pada nilai
    public function updated($property)
    {
        if (Str::startsWith($property, ['nilaiLapangan', 'nilaiPembimbing'])) {
            $this->calculateFinalGrade();
        }
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    // Fungsi untuk menghitung nilai akhir secara otomatis
    public function calculateFinalGrade()
    {
        $totalNilaiLapangan = 0;
        $countLapangan = 0;
        foreach ($this->komponenLapangan as $key => $label) {
            if (is_numeric($this->nilaiLapangan[$key] ?? null)) {
                $totalNilaiLapangan += $this->nilaiLapangan[$key];
                $countLapangan++;
            }
        }
        $rataRataLapangan = $countLapangan > 0 ? $totalNilaiLapangan / $countLapangan : 0;

        $totalNilaiPembimbing = 0;
        $countPembimbing = 0;
        foreach ($this->komponenPembimbing as $key => $label) {
            if (is_numeric($this->nilaiPembimbing[$key] ?? null)) {
                $totalNilaiPembimbing += $this->nilaiPembimbing[$key];
                $countPembimbing++;
            }
        }
        $rataRataPembimbing = $countPembimbing > 0 ? $totalNilaiPembimbing / $countPembimbing : 0;

        $this->nilaiAngkaFinal = ($rataRataLapangan * 0.4) + ($rataRataPembimbing * 0.6);

        // Konversi ke nilai huruf
        $nilai = $this->nilaiAngkaFinal;
        if ($nilai >= 80) $this->nilaiHurufFinal = 'A';
        elseif ($nilai >= 75) $this->nilaiHurufFinal = 'AB';
        elseif ($nilai >= 70) $this->nilaiHurufFinal = 'B';
        elseif ($nilai >= 65) $this->nilaiHurufFinal = 'BC';
        elseif ($nilai >= 60) $this->nilaiHurufFinal = 'C';
        elseif ($nilai >= 56) $this->nilaiHurufFinal = 'CD';
        elseif ($nilai >= 46) $this->nilaiHurufFinal = 'D';
        else $this->nilaiHurufFinal = 'E';
    }

    public function openGradeModal($seminarId)
    {
        $this->seminarToGrade = Seminar::with('kerjaPraktek.mahasiswa', 'penilaians')->findOrFail($seminarId);
        $this->reset(['nilaiLapangan', 'nilaiPembimbing', 'nilaiAngkaFinal', 'nilaiHurufFinal', 'berita_acara_signed']);
        $this->resetErrorBag();

        // Isi form dengan data yang sudah ada jika melakukan edit
        foreach ($this->seminarToGrade->penilaians as $penilaian) {
            if ($penilaian->tipe === 'lapangan') {
                $this->nilaiLapangan[$penilaian->nama_komponen] = $penilaian->nilai;
            } else {
                $this->nilaiPembimbing[$penilaian->nama_komponen] = $penilaian->nilai;
            }
        }
        $this->calculateFinalGrade(); // Hitung ulang nilai saat modal dibuka

        Flux::modal('grade-modal')->show();
    }

    public function saveGrade()
    {
        // Validasi untuk semua input nilai komponen
        $validated = $this->validate([
            'nilaiLapangan.*' => 'required|numeric|min:0|max:100',
            'nilaiPembimbing.*' => 'required|numeric|min:0|max:100',
        ]);

        // Validasi file berita acara secara terpisah dan kondisional
        // File hanya wajib diisi jika belum ada sama sekali
        if (!$this->seminarToGrade->berita_acara_signed && !$this->berita_acara_signed) {
            $this->addError('berita_acara_signed', 'Berkas berita acara wajib diunggah.');
            return;
        }

        if ($this->seminarToGrade) {
            // Gunakan transaksi untuk memastikan semua data tersimpan dengan benar
            DB::transaction(function () {
                // Hapus nilai komponen lama untuk diganti dengan yang baru (saat edit)
                $this->seminarToGrade->penilaians()->delete();

                // Simpan setiap komponen nilai lapangan ke tabel 'penilaians'
                foreach ($this->nilaiLapangan as $komponen => $nilai) {
                    Penilaian::create([
                        'seminar_id' => $this->seminarToGrade->id,
                        'nama_komponen' => $komponen,
                        'nilai' => $nilai,
                        'tipe' => 'lapangan',
                    ]);
                }

                // Simpan setiap komponen nilai pembimbing ke tabel 'penilaians'
                foreach ($this->nilaiPembimbing as $komponen => $nilai) {
                    Penilaian::create([
                        'seminar_id' => $this->seminarToGrade->id,
                        'nama_komponen' => $komponen,
                        'nilai' => $nilai,
                        'tipe' => 'pembimbing',
                    ]);
                }

                // Siapkan data update untuk tabel seminar
                $updateData = [
                    'status_seminar' => 'Dinilai',
                    'nilai_pembimbing_lapangan' => round(collect($this->nilaiLapangan)->avg(), 2),
                    'nilai_dosen_pembimbing' => round(collect($this->nilaiPembimbing)->avg(), 2),
                    'nilai_akhir' => $this->nilaiHurufFinal,
                ];

                // Cek apakah ada file baru yang diunggah untuk menggantikan yang lama
                if ($this->berita_acara_signed) {
                    // Validasi file yang baru diunggah
                    $this->validate(['berita_acara_signed' => 'file|mimes:pdf|max:2048']);

                    // Hapus file lama jika ada
                    if ($this->seminarToGrade->berita_acara_signed) {
                        Storage::disk('public')->delete($this->seminarToGrade->berita_acara_signed);
                    }
                    // Simpan file baru dan tambahkan path ke data update
                    $updateData['berita_acara_signed'] = $this->berita_acara_signed->store('berita-acara-signed', 'public');
                }

                // Update data utama di tabel seminar
                $this->seminarToGrade->update($updateData);

                // Kirim notifikasi ke mahasiswa
                $this->seminarToGrade->kerjaPraktek->mahasiswa->user->notify(new \App\Notifications\SeminarDinilai($this->seminarToGrade));
            });

            Flux::modal('grade-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Penilaian seminar telah disimpan.');
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
    <flux:subheading size="lg" class="mb-6">Upload berita acara dan input nilai akhir seminar mahasiswa.
    </flux:subheading>
    <flux:separator variant="subtle"/>

    {{-- Input Search BARU --}}
    <div class="mt-6 flex">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search"
                        placeholder="Cari berdasarkan nama mahasiswa atau judul KP..." icon="magnifying-glass"/>
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
                        <flux:table.column class="cursor-pointer" wire:click="sortBy('seminars.tanggal_seminar')">Tgl.
                            Seminar
                        </flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->perluDinilai as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->seminar->judul_kp_final, 40) }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($kp->seminar->tanggal_seminar)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="primary"
                                                 wire:click="openGradeModal({{ $kp->seminar->id }})">
                                        Beri Penilaian
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center">Tidak ada seminar yang perlu dinilai.
                                </flux:table.cell>
                            </flux:table.row>
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
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center">Belum ada riwayat penilaian.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Modal untuk Memberi Nilai (DIPERBARUI) --}}
    <flux:modal name="grade-modal" class="md:w-[36rem]">
        @if ($seminarToGrade)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $seminarToGrade->nilai_akhir ? 'Edit' : 'Input' }} Nilai Seminar
                    </flux:heading>
                    <flux:text class="mt-2">
                        Mahasiswa: <span
                            class="font-bold">{{ $seminarToGrade->kerjaPraktek->mahasiswa->nama_mahasiswa }}</span>
                    </flux:text>
                </div>
                <div class="space-y-6">
                    {{-- Nilai Pembimbing Lapangan --}}
                    <flux:card class="space-y-4">
                        <h4 class="font-semibold">Komponen Penilaian Pembimbing Lapangan (Bobot 40%)</h4>
                        @foreach($komponenLapangan as $key => $label)
                            <flux:input wire:model.live="nilaiLapangan.{{ $key }}" type="number" step="1" min="0"
                                        max="100" :label="$label" required/>
                        @endforeach
                    </flux:card>

                    {{-- Nilai Dosen Pembimbing --}}
                    <flux:card class="space-y-4">
                        <h4 class="font-semibold">Komponen Penilaian Dosen Pembimbing (Bobot 60%)</h4>
                        @foreach($komponenPembimbing as $key => $label)
                            <flux:input wire:model.live="nilaiPembimbing.{{ $key }}" type="number" step="1" min="0"
                                        max="100" :label="$label" required/>
                        @endforeach
                    </flux:card>

                    {{-- Upload Berita Acara & Hasil Akhir --}}
                    <flux:card class="space-y-4">
                        <h4 class="font-semibold">Berkas & Hasil Akhir</h4>
                        <flux:input wire:model="berita_acara_signed" type="file"
                                    :label="$seminarToGrade->berita_acara_signed ? 'Ganti Berita Acara (Opsional)' : 'Upload Berita Acara (TTD)'"
                                    helper="File PDF, maks 2MB." :required="!$seminarToGrade->berita_acara_signed"/>

                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div>
                                <flux:label>Nilai Angka Final</flux:label>
                                <div
                                    class="mt-1 flex h-10 w-full items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                    {{ number_format($nilaiAngkaFinal, 2) }}
                                </div>
                            </div>
                            <div>
                                <flux:label>Nilai Huruf Final</flux:label>
                                <div
                                    class="mt-1 flex h-10 w-full items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-2xl font-bold dark:border-zinc-700 dark:bg-zinc-800">
                                    {{ $nilaiHurufFinal ?: '-' }}
                                </div>
                            </div>
                        </div>
                    </flux:card>
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="saveGrade" variant="primary">Simpan Nilai</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
