<?php

use App\Models\KerjaPraktek;
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
use App\Notifications\KpDiteruskanKeKomisi;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SpkTerbit;
use App\Notifications\KpDitolakKomisi;


new #[Title('Validasi Berkas KP')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

// Properti untuk mengontrol tab yang aktif
    public string $tab = 'administrasi';

    // Properti BARU untuk Search, Filter, Sort
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Properti untuk Modal (dirapikan)
    public ?KerjaPraktek $kpToIssueSpk = null;
    public string $tanggalPengambilanSpk = '';

    // Hook dan fungsi-fungsi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'tab'])) {
            $this->resetPage();
        }
    }

    // Fungsi untuk sorting
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // Base query untuk menghindari duplikasi kode
    private function getBaseQuery()
    {
        return KerjaPraktek::with(['mahasiswa.jurusan', 'dosenPembimbing'])
            ->when($this->search, function ($query) {
                $query->where('judul_kp', 'like', '%' . $this->search . '%')
                    ->orWhereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                        ->orWhere('nim', 'like', '%' . $this->search . '%'));
            })
            ->orderBy($this->sortField, $this->sortDirection);
    }

// Query diupdate dengan search & sort
    #[Computed]
    public function reviewAdministrasi()
    {
        return $this->getBaseQuery()->where('status_pengajuan_kp', 'Diajukan')->paginate(10, ['*'], 'administrasiPage');
    }

    #[Computed]
    public function penerbitanSpk()
    {
        return $this->getBaseQuery()->where('status_pengajuan_kp', 'Disetujui')->paginate(10, ['*'], 'spkPage');
    }

    #[Computed]
    public function riwayat()
    {
        return $this->getBaseQuery()
            ->whereIn('status_pengajuan_kp', ['Proses di Komisi', 'Ditolak', 'SPK Terbit'])
            ->when($this->statusFilter, function ($query) {
                $query->where('status_pengajuan_kp', $this->statusFilter);
            })
            ->paginate(10, ['*'], 'riwayatPage');
    }

    public function forwardToKomisi($id)
    {
        KerjaPraktek::findOrFail($id)->update(['status_pengajuan_kp' => 'Proses di Komisi']);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah diteruskan ke Komisi.');

        $kp = KerjaPraktek::findOrFail($id);
        $kp->update(['status_pengajuan_kp' => 'Proses di Komisi']);
        $komisiUsers = User::where('role', 'Dosen Komisi')->get();
        Notification::send($komisiUsers, new KpDiteruskanKeKomisi($kp));
    }

    public function reject($id)
    {
        KerjaPraktek::findOrFail($id)->update([
            'status_pengajuan_kp' => 'Ditolak',
            'catatan_kp' => 'Berkas administrasi tidak lengkap atau tidak sesuai. Silakan ajukan ulang.'
        ]);
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengajuan KP telah ditolak.');
    }

    public function openSpkModal($id)
    {
        // Sekarang hanya perlu mengisi data KP yang akan diproses
        $this->kpToIssueSpk = KerjaPraktek::with('mahasiswa', 'dosenPembimbing')->findOrFail($id);
        Flux::modal('spk-modal')->show();
    }

    public function terbitkanSpk()
    {
        if ($this->kpToIssueSpk) {
            // Validasi tanggal sudah tidak diperlukan lagi
            $this->kpToIssueSpk->update([
                'status_pengajuan_kp' => 'SPK Terbit',
                'tanggal_disetujui_spk' => now(),
                // 'tanggal_pengambilan_spk' dihapus dari sini
            ]);

            // Kirim Notifikasi ke Mahasiswa
            $this->kpToIssueSpk->mahasiswa->user->notify(new SpkTerbit($this->kpToIssueSpk));

            Flux::modal('spk-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'SPK telah diterbitkan dan notifikasi telah dikirim ke mahasiswa.');

            // Kita tidak perlu memicu download untuk Bapendik lagi
            // $this->dispatch('spk-terbit-dan-download', id: $this->kpToIssueSpk->id);

            $this->reset('kpToIssueSpk');
        }
    }

}; ?>

<div x-on:spk-terbit-dan-download.window="window.open('/kerja-praktek/' + $event.detail.id + '/export-spk', '_blank')">
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Validasi Berkas Kerja Praktik</flux:heading>
            <flux:subheading size="lg" class="mb-6">Review kelengkapan administrasi pengajuan KP dari mahasiswa.</flux:subheading>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    {{-- Input Search & Filter --}}
    <div class="mt-6 flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari berdasarkan nama, nim, atau judul KP..." icon="magnifying-glass" />
        </div>
        @if ($tab === 'riwayat')
            <div class="w-full md:w-64">
                <flux:select wire:model.live="statusFilter" placeholder="Filter status riwayat">
                    <option value="">Semua Status Riwayat</option>
                    <option value="Proses di Komisi">Proses di Komisi</option>
                    <option value="Ditolak">Ditolak</option>
                    <option value="SPK Terbit">SPK Terbit</option>
                </flux:select>
            </div>
        @endif
    </div>
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
                <flux:table :paginate="$this->reviewAdministrasi">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul KP</flux:table.column>
                        <flux:table.column>Berkas</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->reviewAdministrasi as $kp)
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

                            {{-- Modal Konfirmasi Teruskan --}}
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
{{--                <div class="border-t p-4 dark:border-neutral-700">--}}
{{--                    <Flux:pagination :paginator="$this->reviewAdministrasi"/>--}}
{{--                </div>--}}
            </flux:card>
        </flux:tab.panel>

        {{-- Panel BARU untuk Tab "Penerbitan SPK" --}}
        <flux:tab.panel name="penerbitan">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->penerbitanSpk">
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
{{--                <div class="border-t p-4 dark:border-neutral-700">--}}
{{--                    <Flux:pagination :paginator="$this->penerbitanSpk"/>--}}
{{--                </div>--}}
            </flux:card>
        </flux:tab.panel>
        {{-- Panel untuk Tab "Riwayat Validasi" --}}
        <flux:tab.panel name="riwayat">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->riwayat">
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
{{--                <div class="border-t p-4 dark:border-neutral-700">--}}
{{--                    <Flux:pagination :paginator="$this->riwayat"/>--}}
{{--                </div>--}}
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>
{{--     Modal BARU untuk menerbitkan SPK--}}
    <flux:modal name="spk-modal" class="md:w-96">
        @if ($kpToIssueSpk)
            <div class="space-y-6 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
                    <flux:icon name="check-circle" class="size-6 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg">Terbitkan SPK?</flux:heading>
                    <flux:text class="mt-2">
                        Anda akan menerbitkan SPK untuk mahasiswa: <br>
                        <span class="font-bold">{{ $kpToIssueSpk->mahasiswa->nama_mahasiswa }}</span>.
                        <br><br>
                        Mahasiswa akan diberi tahu dan dapat mengunduh SPK. Lanjutkan?
                    </flux:text>
                </div>
                <div class="flex justify-center gap-3">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button wire:click="terbitkanSpk" variant="primary">Ya, Terbitkan SPK</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
