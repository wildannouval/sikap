<?php

use App\Models\KerjaPraktek;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new #[Title('Validasi Berkas KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

// Properti untuk mengontrol tab yang aktif
    public string $tab = 'administrasi';

    // Properti untuk Modal Penerbitan SPK
    public ?KerjaPraktek $kpToIssueSpk = null;
    public string $tanggalPengambilanSpk = '';

    #[Computed]
    public function reviewAdministrasi()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan'])
            ->where('status_pengajuan_kp', 'Diajukan')
            ->latest()
            ->paginate(10, ['*'], 'administrasiPage');
    }

    #[Computed]
    public function penerbitanSpk()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan'])
            ->where('status_pengajuan_kp', 'Disetujui')
            ->latest()
            ->paginate(10, ['*'], 'spkPage');
    }

    #[Computed]
    public function riwayat()
    {
        // Sekarang mengambil semua data kerja praktek sebagai riwayat
        return KerjaPraktek::with(['mahasiswa.jurusan'])
            ->latest()
            ->paginate(10, ['*'], 'riwayatPage');
    }

    public function forwardToKomisi($id)
    {
        KerjaPraktek::findOrFail($id)->update(['status_pengajuan_kp' => 'Proses di Komisi']);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah diteruskan ke Komisi.');
    }

    public function reject($id)
    {
        KerjaPraktek::findOrFail($id)->update([
            'status_pengajuan_kp' => 'Ditolak',
            'catatan_kp' => 'Berkas administrasi tidak lengkap atau tidak sesuai. Silakan hubungi Bapendik.'
        ]);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah ditolak.');
    }

    public function openSpkModal($id)
    {
        $this->kpToIssueSpk = KerjaPraktek::findOrFail($id);
        $this->reset('tanggalPengambilanSpk');
        Flux::modal('spk-modal')->show();
    }

    public function terbitkanSpk()
    {
        $this->validate(['tanggalPengambilanSpk' => 'required|date']);

        if ($this->kpToIssueSpk) {
            $this->kpToIssueSpk->update([
                'status_pengajuan_kp' => 'SPK Terbit',
                'tanggal_pengambilan_spk' => $this->tanggalPengambilanSpk,
                'tanggal_disetujui_spk' => now(),
            ]);

            Flux::modal('spk-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'SPK telah diterbitkan.');

            // Kirim event ke browser dengan membawa ID KP
            $this->dispatch('spk-terbit-dan-download', id: $this->kpToIssueSpk->id);

            $this->reset('kpToIssueSpk', 'tanggalPengambilanSpk');
        }
    }

    /**
     * Data untuk tab "Perlu Diproses" (status 'Diajukan').
     */
    #[Computed]
    public function pengajuanKp()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan'])
            ->where('status_pengajuan_kp', 'Diajukan')
            ->latest()
            ->paginate(10, ['*'], 'prosesPage');
    }

    /**
     * Data untuk tab "Riwayat Validasi" (status 'Proses di Komisi' atau 'Ditolak').
     */
    #[Computed]
    public function riwayatValidasi()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan'])
            ->whereIn('status_pengajuan_kp', ['Proses di Komisi', 'Ditolak'])
            ->latest()
            ->paginate(10, ['*'], 'riwayatPage');
    }

    /**
     * Fungsi untuk mengunduh file.
     */
    public function downloadFile($path)
    {
        return response()->download(storage_path('app/public/' . $path));
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Validasi Berkas Kerja Praktik</flux:heading>
            <flux:subheading size="lg" class="mb-6">Review kelengkapan administrasi pengajuan KP dari mahasiswa.</flux:subheading>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    {{-- Grup Tab --}}
    <flux:tab.group class="mt-4">
        <flux:tabs wire:model.live="tab">
            <flux:tab name="administrasi">Review Administrasi</flux:tab>
            <flux:tab name="penerbitan">Penerbitan SPK</flux:tab>
            <flux:tab name="riwayat">Riwayat</flux:tab>
        </flux:tabs>

        {{-- Panel untuk Tab "Perlu Diproses" --}}
        <flux:tab.panel name="administrasi">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Berkas</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->pengajuanKp as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button as="a" href="{{ asset('storage/' . $kp->proposal_kp) }}" target="_blank" size="xs" icon="document-arrow-down">Proposal</flux:button>
                                        <flux:button as="a" href="{{ asset('storage/' . $kp->surat_keterangan_kp) }}" target="_blank" size="xs" icon="document-arrow-down">Surat Ket.</flux:button>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:modal.trigger :name="'reject-kp-' . $kp->id">
                                            <flux:button size="xs" variant="danger">Tolak</flux:button>
                                        </flux:modal.trigger>
                                        <flux:modal.trigger :name="'forward-kp-' . $kp->id">
                                            <flux:button size="xs" variant="primary">Teruskan ke Komisi</flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>

                            {{-- Modal Konfirmasi Tolak --}}
                            <flux:modal :name="'reject-kp-' . $kp->id" class="md:w-96">
                                <div class="space-y-6 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                        <flux:icon name="x-mark" class="size-6 text-red-600"/>
                                    </div>
                                    <div>
                                        <flux:heading size="lg">Tolak Pengajuan KP?</flux:heading>
                                        <flux:text class="mt-2">
                                            Anda yakin ingin menolak pengajuan KP dari <span class="font-bold">{{ $kp->mahasiswa->nama_mahasiswa }}</span>?
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                        <flux:button variant="danger" wire:click="reject({{ $kp->id }})">Ya, Tolak</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                            {{-- Tambahkan modal ini di bawah modal 'reject-kp' --}}
                            <flux:modal :name="'forward-kp-' . $kp->id" class="md:w-96">
                                <div class="space-y-6 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-blue-100">
                                        <flux:icon name="paper-airplane" class="size-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <flux:heading size="lg">Teruskan ke Komisi?</flux:heading>
                                        <flux:text class="mt-2">
                                            Anda yakin ingin meneruskan pengajuan KP ini untuk direview lebih lanjut oleh Komisi?
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                        <flux:button variant="primary" wire:click="forwardToKomisi({{ $kp->id }})">Ya, Teruskan</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-neutral-500">
                                    Tidak ada pengajuan KP yang perlu divalidasi.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">
                    <Flux:pagination :paginator="$this->pengajuanKp"/>
                </div>
            </flux:card>
        </flux:tab.panel>

        {{-- Panel BARU untuk Tab "Penerbitan SPK" --}}
        <flux:tab.panel name="penerbitan">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Tgl. Disetujui Komisi</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->penerbitanSpk as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>{{ \Carbon\Carbon::parse($kp->tanggal_disetujui_kp)->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="xs" variant="primary" wire:click="openSpkModal({{ $kp->id }})">
                                        Terbitkan SPK
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center text-neutral-500">
                                    Tidak ada pengajuan yang perlu diterbitkan SPK-nya.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">
                    {{ $this->penerbitanSpk->links() }}
                </div>
            </flux:card>
        </flux:tab.panel>

        {{-- Panel untuk Tab "Riwayat Validasi" --}}
        <flux:tab.panel name="riwayat">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        {{-- KOLOM BARU --}}
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->riwayat as $kp)
                            <flux:table.row :key="'riwayat-' . $kp->id">
                                <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>
                                    @php
                                        $color = match($kp->status_pengajuan_kp) {
                                            'Diajukan' => 'yellow',
                                            'Proses di Komisi' => 'blue',
                                            'Disetujui' => 'green',
                                            'Ditolak' => 'red',
                                            'SPK Terbit' => 'emerald',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge :color="$color" size="sm">{{ $kp->status_pengajuan_kp }}</flux:badge>
                                </flux:table.cell>
                                {{-- DATA BARU --}}
                                <flux:table.cell>
                                    @if ($kp->status_pengajuan_kp === 'SPK Terbit')
                                        <flux:button
                                            as="a"
                                            href="{{ route('kp.export-spk', $kp->id) }}"
                                            size="xs"
                                            variant="ghost"
                                            icon="arrow-down-tray">
                                            Ekspor SPK
                                        </flux:button>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center">Belum ada riwayat pengajuan KP.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">
                    {{ $this->riwayat->links() }}
                </div>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    {{-- Tabel Daftar Pengajuan --}}
{{--    <flux:card class="mt-8">--}}
{{--        <flux:table>--}}
{{--            <flux:table.columns>--}}
{{--                <flux:table.column>Nama Mahasiswa</flux:table.column>--}}
{{--                <flux:table.column>Judul KP</flux:table.column>--}}
{{--                <flux:table.column>Tgl. Pengajuan</flux:table.column>--}}
{{--                <flux:table.column>Berkas</flux:table.column>--}}
{{--                <flux:table.column>Aksi</flux:table.column>--}}
{{--            </flux:table.columns>--}}
{{--            <flux:table.rows>--}}
{{--                @forelse ($this->pengajuanKp as $kp)--}}
{{--                    <flux:table.row :key="$kp->id">--}}
{{--                        <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>--}}
{{--                        <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>--}}
{{--                        <flux:table.cell>{{ \Carbon\Carbon::parse($kp->tanggal_pengajuan_kp)->format('d/m/Y') }}</flux:table.cell>--}}
{{--                        <flux:table.cell>--}}
{{--                            <div class="flex items-center gap-2">--}}
{{--                                <flux:button size="xs" icon="document-arrow-down" wire:click="downloadFile('{{ $kp->proposal_kp }}')" target="_blank">Proposal</flux:button>--}}
{{--                                <flux:button size="xs" icon="document-arrow-down" wire:click="downloadFile('{{ $kp->surat_keterangan_kp }}')" target="_blank">Surat Ket.</flux:button>--}}
{{--                            </div>--}}
{{--                        </flux:table.cell>--}}
{{--                        <flux:table.cell>--}}
{{--                            <div class="flex items-center justify-center gap-2">--}}
{{--                                <flux:modal.trigger :name="'reject-kp-' . $kp->id">--}}
{{--                                    <flux:button size="xs" variant="danger">--}}
{{--                                        Tolak--}}
{{--                                    </flux:button>--}}
{{--                                </flux:modal.trigger>--}}

{{--                                --}}{{-- Tombol "Teruskan ke Komisi" sekarang menjadi pemicu modal --}}
{{--                                <flux:modal.trigger :name="'forward-kp-' . $kp->id">--}}
{{--                                    <flux:button size="xs" variant="primary">--}}
{{--                                        Teruskan ke Komisi--}}
{{--                                    </flux:button>--}}
{{--                                </flux:modal.trigger>--}}
{{--                            </div>--}}
{{--                        </flux:table.cell>--}}
{{--                    </flux:table.row>--}}
{{--                    --}}{{-- Modal Konfirmasi Tolak --}}
{{--                    <flux:modal :name="'reject-kp-' . $kp->id" class="md:w-96">--}}
{{--                        <div class="space-y-6 text-center">--}}
{{--                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">--}}
{{--                                <flux:icon name="x-mark" class="size-6 text-red-600"/>--}}
{{--                            </div>--}}
{{--                            <div>--}}
{{--                                <flux:heading size="lg">Tolak Pengajuan KP?</flux:heading>--}}
{{--                                <flux:text class="mt-2">--}}
{{--                                    Anda yakin ingin menolak pengajuan KP dari <span class="font-bold">{{ $kp->mahasiswa->nama_mahasiswa }}</span>?--}}
{{--                                </flux:text>--}}
{{--                            </div>--}}
{{--                            <div class="flex justify-center gap-3">--}}
{{--                                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>--}}
{{--                                <flux:button variant="danger" wire:click="reject({{ $kp->id }})">Ya, Tolak</flux:button>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </flux:modal>--}}
{{--                    --}}{{-- Tambahkan modal ini di bawah modal 'reject-kp' --}}
{{--                    <flux:modal :name="'forward-kp-' . $kp->id" class="md:w-96">--}}
{{--                        <div class="space-y-6 text-center">--}}
{{--                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-blue-100">--}}
{{--                                <flux:icon name="paper-airplane" class="size-6 text-blue-600" />--}}
{{--                            </div>--}}
{{--                            <div>--}}
{{--                                <flux:heading size="lg">Teruskan ke Komisi?</flux:heading>--}}
{{--                                <flux:text class="mt-2">--}}
{{--                                    Anda yakin ingin meneruskan pengajuan KP ini untuk direview lebih lanjut oleh Komisi?--}}
{{--                                </flux:text>--}}
{{--                            </div>--}}
{{--                            <div class="flex justify-center gap-3">--}}
{{--                                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>--}}
{{--                                <flux:button variant="primary" wire:click="forwardToKomisi({{ $kp->id }})">Ya, Teruskan</flux:button>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </flux:modal>--}}
{{--                @empty--}}
{{--                    <flux:table.row>--}}
{{--                        <flux:table.cell colspan="5" class="text-center text-neutral-500">--}}
{{--                            Tidak ada pengajuan KP yang perlu divalidasi.--}}
{{--                        </flux:table.cell>--}}
{{--                    </flux:table.row>--}}
{{--                @endforelse--}}
{{--            </flux:table.rows>--}}
{{--        </flux:table>--}}
{{--        <div class="border-t p-4 dark:border-neutral-700">--}}
{{--            <flux:pagination :paginator="$this->pengajuanKp"/>--}}
{{--        </div>--}}
{{--    </flux:card>--}}

    {{-- Modal BARU untuk menerbitkan SPK --}}
    <flux:modal name="spk-modal" class="md:w-96">
        @if ($kpToIssueSpk)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Penerbitan SPK</flux:heading>
                    <flux:text class="mt-2">
                        Input tanggal pengambilan SPK untuk mahasiswa: <span class="font-bold">{{ $kpToIssueSpk->mahasiswa->nama_mahasiswa }}</span>.
                    </flux:text>
                </div>
                <div>
                    <flux:input type="date" wire:model="tanggalPengambilanSpk" label="Tanggal Pengambilan SPK" required />
                    @error('tanggalPengambilanSpk') <span class="mt-1 text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="terbitkanSpk" variant="primary">Simpan & Terbitkan</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
