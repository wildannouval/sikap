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

    public array $nilaiLapangan = [];
    public array $nilaiPembimbing = [];
    public float $nilaiAngkaFinal = 0;
    public string $nilaiHurufFinal = '';

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

    public function updated($property)
    {
        if (Str::startsWith($property, ['nilaiLapangan', 'nilaiPembimbing'])) {
            $this->calculateFinalGrade();
        }
        if ($property === 'search' || $property === 'tab') {
            $this->resetPage();
        }
    }

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

        foreach ($this->seminarToGrade->penilaians as $penilaian) {
            if ($penilaian->tipe === 'lapangan') {
                $this->nilaiLapangan[$penilaian->nama_komponen] = $penilaian->nilai;
            } else {
                $this->nilaiPembimbing[$penilaian->nama_komponen] = $penilaian->nilai;
            }
        }
        $this->calculateFinalGrade();
        Flux::modal('grade-modal')->show();
    }

    public function saveGrade()
    {
        $validated = $this->validate([
            'nilaiLapangan.*' => 'required|numeric|min:0|max:100',
            'nilaiPembimbing.*' => 'required|numeric|min:0|max:100',
        ]);
        if (!$this->seminarToGrade->berita_acara_signed && !$this->berita_acara_signed) {
            $this->addError('berita_acara_signed', 'Berkas berita acara wajib diunggah.');
            return;
        }

        if ($this->seminarToGrade) {
            DB::transaction(function () {
                $this->seminarToGrade->penilaians()->delete();
                foreach ($this->nilaiLapangan as $komponen => $nilai) {
                    Penilaian::create(['seminar_id' => $this->seminarToGrade->id, 'nama_komponen' => $komponen, 'nilai' => $nilai, 'tipe' => 'lapangan']);
                }
                foreach ($this->nilaiPembimbing as $komponen => $nilai) {
                    Penilaian::create(['seminar_id' => $this->seminarToGrade->id, 'nama_komponen' => $komponen, 'nilai' => $nilai, 'tipe' => 'pembimbing']);
                }
                $updateData = [
                    'status_seminar' => 'Dinilai',
                    'nilai_pembimbing_lapangan' => round(collect($this->nilaiLapangan)->avg(), 2),
                    'nilai_dosen_pembimbing' => round(collect($this->nilaiPembimbing)->avg(), 2),
                    'nilai_akhir' => $this->nilaiHurufFinal,
                ];
                if ($this->berita_acara_signed) {
                    $this->validate(['berita_acara_signed' => 'file|mimes:pdf|max:2048']);
                    if ($this->seminarToGrade->berita_acara_signed) {
                        Storage::disk('public')->delete($this->seminarToGrade->berita_acara_signed);
                    }
                    $updateData['berita_acara_signed'] = $this->berita_acara_signed->store('berita-acara-signed', 'public');
                }
                $this->seminarToGrade->update($updateData);
                $this->seminarToGrade->kerjaPraktek->mahasiswa->user->notify(new \App\Notifications\SeminarDinilai($this->seminarToGrade));
            });
            Flux::modal('grade-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Penilaian seminar telah disimpan.');
        }
    }
    
    private function getBaseQuery()
    {
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) {
            return KerjaPraktek::where('id', -1);
        }
        return KerjaPraktek::with('mahasiswa', 'seminar')
            ->where('dosen_pembimbing_id', $dosenId)
            ->when($this->search, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%'))
                    ->orWhere('judul_kp', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }
    
    #[Computed]
    public function perluDinilai()
    {
        return $this->getBaseQuery()
            ->whereHas('seminar', fn($q) => $q->whereIn('status_seminar', ['Dijadwalkan', 'Selesai']))
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
    <div class="mb-6">
        <flux:heading size="xl" level="1">Penilaian Kerja Praktik</flux:heading>
        <flux:subheading size="lg">Upload berita acara dan input nilai akhir seminar mahasiswa bimbingan Anda.</flux:subheading>
    </div>

    {{-- [START] PERUBAHAN LAYOUT MENJADI DUA KOLOM --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        {{-- Kolom Kiri (Utama): Tabel dan Tab --}}
        <div class="lg:col-span-2 space-y-6">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari mahasiswa atau judul KP..." icon="magnifying-glass" />
            
            <flux:tab.group>
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
                                <flux:table.column>Tgl. Seminar</flux:table.column>
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
                                    <flux:table.row>
                                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                            Tidak ada seminar yang perlu dinilai.
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
                                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                            Belum ada riwayat penilaian.
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
        
        {{-- Kolom Kanan (Informasi) --}}
        <div class="lg:col-span-1 space-y-8">
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Alur Kerja Penilaian</h3>
                <ol class="list-decimal list-inside space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>
                        <b>Pilih Mahasiswa:</b><br>
                        Di tab "Perlu Dinilai", klik tombol "Beri Penilaian" untuk mahasiswa yang seminarnya telah selesai dilaksanakan.
                    </li>
                    <li>
                        <b>Input Nilai:</b><br>
                        Isi semua komponen nilai, baik dari Pembimbing Lapangan maupun dari Anda sebagai Dosen Pembimbing. Nilai angka dan huruf final akan terhitung otomatis.
                    </li>
                    <li>
                        <b>Unggah Berita Acara:</b><br>
                        Unggah Berita Acara (BAP) yang telah ditandatangani dalam format PDF.
                    </li>
                    <li>
                        <b>Simpan Penilaian:</b><br>
                        Klik "Simpan Nilai" untuk menyelesaikan. Data akan pindah ke tab "Riwayat Penilaian" dan mahasiswa akan mendapat notifikasi.
                    </li>
                </ol>
            </flux:card>
             <flux:card>
                <h3 class="text-lg font-semibold mb-4">Bobot Nilai</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                        <span class="text-zinc-600 dark:text-zinc-400">Pembimbing Lapangan</span>
                        <span class="font-bold text-lg">40%</span>
                    </div>
                     <div class="flex justify-between items-center p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                        <span class="text-zinc-600 dark:text-zinc-400">Dosen Pembimbing</span>
                        <span class="font-bold text-lg">60%</span>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
    {{-- [END] PERUBAHAN LAYOUT --}}

    <flux:modal name="grade-modal" class="md:w-[36rem]">
        @if ($seminarToGrade)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $seminarToGrade->nilai_akhir ? 'Edit' : 'Input' }} Nilai Seminar</flux:heading>
                    <flux:text class="mt-2">
                        Mahasiswa: <span class="font-bold">{{ $seminarToGrade->kerjaPraktek->mahasiswa->nama_mahasiswa }}</span>
                    </flux:text>
                </div>
                <div class="space-y-6 max-h-[60vh] overflow-y-auto pr-2">
                    <flux:card class="space-y-4">
                        <h4 class="font-semibold">Komponen Penilaian Pembimbing Lapangan (Bobot 40%)</h4>
                        @foreach($komponenLapangan as $key => $label)
                            <flux:input wire:model.live="nilaiLapangan.{{ $key }}" type="number" step="1" min="0" max="100" :label="$label" required/>
                        @endforeach
                    </flux:card>
                    <flux:card class="space-y-4">
                        <h4 class="font-semibold">Komponen Penilaian Dosen Pembimbing (Bobot 60%)</h4>
                        @foreach($komponenPembimbing as $key => $label)
                            <flux:input wire:model.live="nilaiPembimbing.{{ $key }}" type="number" step="1" min="0" max="100" :label="$label" required/>
                        @endforeach
                    </flux:card>
                    <flux:card class="space-y-4">
                        <h4 class="font-semibold">Berkas & Hasil Akhir</h4>
                        <flux:input wire:model="berita_acara_signed" type="file"
                                    :label="$seminarToGrade->berita_acara_signed ? 'Ganti Berita Acara (Opsional)' : 'Upload Berita Acara (TTD)'"
                                    helper="File PDF, maks 2MB." :required="!$seminarToGrade->berita_acara_signed"/>
                        
                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div>
                                <flux:label>Nilai Angka Final</flux:label>
                                <div class="mt-1 flex h-10 w-full items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                    {{ number_format($nilaiAngkaFinal, 2) }}
                                </div>
                            </div>
                            <div>
                                <flux:label>Nilai Huruf Final</flux:label>
                                <div class="mt-1 flex h-10 w-full items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-2xl font-bold dark:border-zinc-700 dark:bg-zinc-800">
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