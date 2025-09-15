<?php

use App\Models\KerjaPraktek;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Title('Mahasiswa Bimbingan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    // Properti BARU untuk Search, Filter, Sort
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $sortField = 'created_at';
    #[Url]
    public string $sortDirection = 'desc';

    // Hook BARU untuk reset paginasi
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter'])) {
            $this->resetPage();
        }
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

    #[Computed]
    public function mahasiswaBimbingan()
    {
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) {
            return KerjaPraktek::where('id', -1)->paginate(10);
        }

        return KerjaPraktek::with('mahasiswa')
            ->where('dosen_pembimbing_id', $dosenId)
            ->where('status_pengajuan_kp', 'SPK Terbit')
            ->when($this->search, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                    ->orWhere('nim', 'like', '%' . $this->search . '%'));
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status_kp', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Daftar Mahasiswa Bimbingan</flux:heading>
        <flux:subheading size="lg">Kelola dan pantau progres Kerja Praktik mahasiswa yang Anda bimbing.</flux:subheading>
    </div>

    {{-- [START] PERUBAHAN LAYOUT MENJADI DUA KOLOM --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        {{-- Kolom Kiri (Utama): Tabel dan Aksi --}}
        <div class="lg:col-span-2 space-y-6">
            <flux:card>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
                    <div class="flex-1 w-full sm:w-auto">
                        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau NIM mahasiswa..." icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-56">
                        <flux:select wire:model.live="statusFilter">
                            <option value="">Semua Status KP</option>
                            <option value="Berlangsung">Berlangsung</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Batal">Batal</option>
                        </flux:select>
                    </div>
                </div>

                <flux:table :paginate="$this->mahasiswaBimbingan">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>Judul Kerja Praktik</flux:table.column>
                        <flux:table.column>Status KP</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->mahasiswaBimbingan as $kp)
                            <flux:table.row :key="$kp->id">
                                <flux:table.cell variant="strong">
                                    {{ $kp->mahasiswa->nama_mahasiswa }}
                                    <span class="block text-xs font-normal text-zinc-500">{{ $kp->mahasiswa->nim }}</span>
                                </flux:table.cell>
                                <flux:table.cell>{{ Str::limit($kp->judul_kp, 40) }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($kp->status_kp)
                                        @php
                                            $color = match($kp->status_kp) {
                                                'Berlangsung' => 'blue',
                                                'Selesai' => 'green',
                                                'Batal' => 'red',
                                                default => 'zinc',
                                            };
                                        @endphp
                                        <flux:badge :color="$color" size="sm">{{ $kp->status_kp }}</flux:badge>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button as="a" href="{{ route('dospem.bimbingan.detail', $kp->id) }}" size="xs" variant="primary">
                                        Lihat Logbook
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                    Tidak ada data mahasiswa bimbingan ditemukan.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>

        {{-- Kolom Kanan (Informasi) --}}
        <div class="lg:col-span-1 space-y-8">
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Informasi & Alur Kerja</h3>
                <div class="space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <p>Halaman ini menampilkan daftar semua mahasiswa yang telah ditugaskan kepada Anda sebagai Dosen Pembimbing.</p>
                    <ol class="list-decimal list-inside space-y-2 pl-1">
                        <li>
                           Tugas utama Anda adalah memantau progres KP dan memverifikasi catatan bimbingan (logbook) yang dikirim oleh mahasiswa.
                        </li>
                        <li>
                            Klik tombol <b>"Lihat Logbook"</b> pada setiap mahasiswa untuk melihat detail catatan bimbingan mereka dan melakukan verifikasi.
                        </li>
                        <li>
                            Pastikan Anda memberikan catatan yang jelas jika bimbingan memerlukan <b>Revisi</b>.
                        </li>
                         <li>
                            Jumlah bimbingan yang terverifikasi akan menjadi syarat bagi mahasiswa untuk dapat mendaftar seminar.
                        </li>
                    </ol>
                </div>
            </flux:card>
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Makna Status KP</h3>
                 <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <flux:badge color="blue" class="mt-0.5">Berlangsung</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Mahasiswa sedang aktif melaksanakan Kerja Praktik dan proses bimbingan.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:badge color="green" class="mt-0.5">Selesai</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Mahasiswa telah menyelesaikan seluruh rangkaian KP, termasuk seminar dan distribusi laporan.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:badge color="red" class="mt-0.5">Batal</flux:badge>
                        <p class="text-zinc-600 dark:text-zinc-400">Proses Kerja Praktik mahasiswa ini dibatalkan.</p>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
    {{-- [END] PERUBAHAN LAYOUT --}}
</div>