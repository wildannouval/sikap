<?php

use App\Models\Distribusi;
use App\Models\KerjaPraktek;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

new #[Title('Nilai Akhir KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public ?KerjaPraktek $kerjaPraktek = null;
    public $berkas_distribusi;

    public function mount()
    {
        $mahasiswaId = Auth::user()->mahasiswa?->id;
        if ($mahasiswaId) {
            // Cari KP yang seminarnya sudah dinilai
            $this->kerjaPraktek = KerjaPraktek::with(['seminar', 'dosenPembimbing', 'distribusi'])
                ->where('mahasiswa_id', $mahasiswaId)
                ->whereHas('seminar', function ($query) {
                    $query->where('status_seminar', 'Dinilai');
                })
                ->first();
        }
    }

    public function uploadDistribusi()
    {
        $validated = $this->validate([
            'berkas_distribusi' => 'required|file|mimes:pdf|max:2048' // PDF, maks 2MB
        ]);

        if ($this->kerjaPraktek) {
            $filePath = $this->berkas_distribusi->store('bukti-distribusi', 'public');

            Distribusi::create([
                'kerja_praktek_id' => $this->kerjaPraktek->id,
                'mahasiswa_id' => $this->kerjaPraktek->mahasiswa_id,
                'berkas_distribusi' => $filePath,
                'tanggal_distribusi' => now(),
            ]);

            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Bukti distribusi laporan berhasil diunggah.');
            // Refresh data KP untuk menampilkan info file yang baru diunggah
            $this->kerjaPraktek = $this->kerjaPraktek->fresh(['seminar', 'dosenPembimbing', 'distribusi']);
        }
    }
}; ?>

<div>
    <flux:heading size="xl" level="1">Nilai Akhir Kerja Praktik</flux:heading>

    @if ($kerjaPraktek && $kerjaPraktek->seminar)
        <flux:subheading size="lg" class="mb-6">Hasil akhir dari pelaksanaan Kerja Praktik Anda.</flux:subheading>
        <flux:separator variant="subtle"/>

        {{-- Kartu Nilai --}}
        <flux:card class="mt-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Detail KP --}}
                <div class="md:col-span-2 space-y-4">
                    <div>
                        <flux:label>Judul KP</flux:label>
                        <p class="font-semibold">{{ $kerjaPraktek->seminar->judul_kp_final }}</p>
                    </div>
                    <div>
                        <flux:label>Dosen Pembimbing</flux:label>
                        <p class="font-semibold">{{ $kerjaPraktek->dosenPembimbing->nama_dosen }}</p>
                    </div>
                    <div>
                        <flux:button as="a" href="{{ asset('storage/' . $kerjaPraktek->seminar->berita_acara_signed) }}" target="_blank" variant="ghost" icon="document-arrow-down">
                            Unduh Berita Acara
                        </flux:button>
                    </div>
                </div>
                {{-- Nilai --}}
                <div class="flex flex-col items-center justify-center rounded-xl bg-green-50 p-6 dark:bg-green-900/20">
                    <flux:label>Nilai Akhir</flux:label>
                    <p class="text-6xl font-bold text-green-600">{{ $kerjaPraktek->seminar->nilai_seminar }}</p>
                </div>
            </div>
        </flux:card>

        {{-- Kartu Upload Bukti Distribusi --}}
        <div class="mt-8">
            <flux:heading size="lg">Upload Bukti Distribusi Laporan</flux:heading>
            <flux:card class="mt-4">
                @if ($kerjaPraktek->distribusi)
                    <div class="text-center">
                        <flux:icon name="check-circle" class="mx-auto size-12 text-green-500" />
                        <p class="mt-4 font-semibold">Anda sudah mengunggah bukti distribusi laporan.</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            Diunggah pada: {{ \Carbon\Carbon::parse($kerjaPraktek->distribusi->tanggal_distribusi)->format('d F Y') }}
                        </p>
                        <flux:button as="a" href="{{ asset('storage/' . $kerjaPraktek->distribusi->berkas_distribusi) }}" target="_blank" variant="ghost" class="!text-indigo-600 !p-0 hover:underline mt-2">
                            Lihat File
                        </flux:button>
                    </div>
                @else
                    <form wire:submit="uploadDistribusi" enctype="multipart/form-data" class="space-y-4">
                        <p class="text-sm">Silakan unggah bukti distribusi laporan (misal: lembar pengesahan) dalam format PDF untuk menyelesaikan seluruh rangkaian Kerja Praktik.</p>
                        <flux:input wire:model="berkas_distribusi" type="file" required />
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">Upload</flux:button>
                        </div>
                    </form>
                @endif
            </flux:card>
        </div>
    @else
        <flux:card class="mt-8 text-center">
            <p>Nilai akhir Anda belum tersedia.</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Nilai akan muncul di sini setelah seminar Anda selesai dan dinilai oleh dosen pembimbing.</p>
        </flux:card>
    @endif
</div>
