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
            $this->kerjaPraktek = KerjaPraktek::with(['seminar', 'dosenPembimbing', 'distribusi'])
                ->where('mahasiswa_id', $mahasiswaId)
                ->whereHas('seminar', function ($query) {
                    $query->where('status_seminar', 'Dinilai');
                })
                ->first();
        }
    }

    /**
     * Fungsi BARU untuk membatalkan pilihan file distribusi.
     */
    public function removeDistribusi()
    {
        $this->berkas_distribusi = null;
        $this->resetErrorBag('berkas_distribusi');
    }

    public function uploadDistribusi()
    {
        $validated = $this->validate([
            'berkas_distribusi' => 'required|file|mimes:pdf|max:2048'
        ]);

        if ($this->kerjaPraktek) {
            $filePath = $this->berkas_distribusi->store('bukti-distribusi', 'public');

            Distribusi::create([
                'kerja_praktek_id' => $this->kerjaPraktek->id,
                'mahasiswa_id' => $this->kerjaPraktek->mahasiswa_id,
                'berkas_distribusi' => $filePath,
                'tanggal_distribusi' => now(),
            ]);

            $this->kerjaPraktek->update(['status_kp' => 'Selesai']);

            // Perintahkan modal konfirmasi untuk menutup
            Flux::modal('confirm-distribusi-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Bukti distribusi laporan berhasil diunggah. Selamat, KP Anda telah selesai!');

            // Refresh data KP untuk menampilkan status terbaru
            $this->kerjaPraktek = $this->kerjaPraktek->fresh(['seminar', 'dosenPembimbing', 'distribusi']);
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Nilai Akhir Kerja Praktik</flux:heading>
        <flux:subheading size="lg">Hasil akhir dari pelaksanaan Kerja Praktik Anda.</flux:subheading>
    </div>

    @if ($kerjaPraktek && $kerjaPraktek->seminar)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-2 space-y-8">
                <flux:card>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="md:col-span-2 space-y-6">
                            <h3 class="text-lg font-semibold">Rincian Penilaian</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Nilai Dosen Pembimbing</span>
                                    <span class="font-bold text-lg">{{ $kerjaPraktek->seminar->nilai_dosen_pembimbing ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Nilai Pembimbing Lapangan</span>
                                    <span class="font-bold text-lg">{{ $kerjaPraktek->seminar->nilai_pembimbing_lapangan ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-center justify-center rounded-xl bg-green-50 p-6 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                            <flux:label>Nilai Akhir (Indeks)</flux:label>
                            <p class="text-7xl font-bold text-green-600 dark:text-green-400">{{ $kerjaPraktek->seminar->nilai_akhir }}</p>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    @if ($kerjaPraktek->distribusi)
                        <div class="text-center p-6">
                            <flux:icon name="check-badge" class="mx-auto size-12 text-green-500" />
                            <h3 class="mt-4 text-lg font-semibold">Proses KP Telah Selesai</h3>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                Anda telah mengunggah bukti distribusi laporan pada 
                                <span class="font-semibold">{{ \Carbon\Carbon::parse($kerjaPraktek->distribusi->tanggal_distribusi)->isoFormat('D MMMM Y') }}</span>.
                                <br>
                                Selamat, seluruh rangkaian Kerja Praktik Anda telah berakhir!
                            </p>
                            {{-- [FIX] Mengganti variant="secondary" menjadi "ghost" --}}
                            <flux:button as="a" href="{{ asset('storage/' . $kerjaPraktek->distribusi->berkas_distribusi) }}" target="_blank" variant="ghost" class="mt-4" icon="eye">
                                Lihat Bukti Unggahan
                            </flux:button>
                        </div>
                    @else
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Langkah Terakhir: Upload Bukti Distribusi</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">Untuk menyelesaikan seluruh administrasi KP, silakan unggah bukti distribusi laporan (misalnya: lembar pengesahan yang sudah ditandatangani) dalam format PDF.</p>
                            <div>
                                <flux:input wire:model="berkas_distribusi" type="file" required />
                                @if ($berkas_distribusi && !$errors->has('berkas_distribusi'))
                                    <div class="mt-2 flex items-center justify-between rounded-lg border bg-zinc-50 p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                        <span class="truncate">{{ $berkas_distribusi->getClientOriginalName() }}</span>
                                        <button type="button" wire:click="removeDistribusi" class="text-red-500 hover:text-red-700 font-bold text-lg flex-shrink-0 ml-2">&times;</button>
                                    </div>
                                @endif
                                @error('berkas_distribusi') <span class="text-sm text-red-500 mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex justify-end">
                                <flux:modal.trigger name="confirm-distribusi-modal">
                                    <flux:button type="button" variant="primary" :disabled="!$berkas_distribusi">
                                        Upload Bukti
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="lg:col-span-1 space-y-8">
                <flux:card>
                     <h3 class="text-lg font-semibold mb-4">Informasi Kerja Praktik</h3>
                     <div class="space-y-4 text-sm">
                        <div>
                            <flux:label>Judul KP</flux:label>
                            <p class="font-semibold">{{ $kerjaPraktek->seminar->judul_kp_final }}</p>
                        </div>
                        <div>
                            <flux:label>Dosen Pembimbing</flux:label>
                            <p class="font-semibold">{{ $kerjaPraktek->dosenPembimbing->nama_dosen }}</p>
                        </div>
                        <div>
                            @if($kerjaPraktek->seminar->berita_acara_signed)
                                <flux:button as="a" href="{{ asset('storage/' . $kerjaPraktek->seminar->berita_acara_signed) }}" target="_blank" variant="ghost" icon="document-arrow-down">
                                    Unduh Berita Acara
                                </flux:button>
                            @endif
                        </div>
                     </div>
                </flux:card>
            </div>
        </div>

    @else
        <flux:separator variant="subtle" class="my-6"/>
        <flux:card class="mt-8 text-center">
            <flux:icon name="academic-cap" class="mx-auto size-12 text-zinc-400" />
            <p class="mt-4 font-semibold">Nilai Akhir Anda Belum Tersedia</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Nilai akan muncul di sini setelah seminar Anda selesai dan dinilai oleh dosen pembimbing.</p>
            <flux:button as="a" href="{{ route('seminar.pendaftaran') }}" variant="primary" size="sm" class="mt-4">
                Lihat Status Seminar
            </flux:button>
        </flux:card>
    @endif

    <flux:modal name="confirm-distribusi-modal" class="md:w-96">
        <div class="space-y-6 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-blue-100">
                <flux:icon name="document-arrow-up" class="size-6 text-blue-600" />
            </div>
            <div>
                <flux:heading size="lg">Konfirmasi Upload</flux:heading>
                <flux:text class="mt-2">
                    Anda akan mengunggah bukti distribusi. Tindakan ini akan menyelesaikan proses KP Anda dan tidak dapat diubah. Lanjutkan?
                </flux:text>
            </div>
            <div class="flex justify-center gap-3">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="uploadDistribusi">Ya, Selesaikan KP</flux:button>
            </div>
        </div>
    </flux:modal>
</div>