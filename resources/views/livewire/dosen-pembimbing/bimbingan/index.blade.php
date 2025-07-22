<?php

use App\Models\KerjaPraktek;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

new #[Title('Mahasiswa Bimbingan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Computed]
    public function mahasiswaBimbingan()
    {
        $dosenId = Auth::user()->dosen?->id;
        if (!$dosenId) {
            return KerjaPraktek::where('id', -1)->paginate(10);
        }

        // Ambil data KP yang dibimbing oleh dosen yang sedang login
        return KerjaPraktek::with('mahasiswa')
            ->where('dosen_pembimbing_id', $dosenId)
            ->where('status_pengajuan_kp', 'SPK Terbit') // Hanya tampilkan yang KP-nya sudah aktif
            ->paginate(10);
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <flux:heading size="xl" level="1">Daftar Mahasiswa Bimbingan</flux:heading>
    <flux:subheading size="lg" class="mb-6">Daftar mahasiswa yang sedang Anda bimbing dalam Kerja Praktik.</flux:subheading>
    <flux:separator variant="subtle"/>

    {{-- Tabel Daftar Mahasiswa --}}
    <flux:card class="mt-8">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nama Mahasiswa</flux:table.column>
                <flux:table.column>NIM</flux:table.column>
                <flux:table.column>Judul Kerja Praktik</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->mahasiswaBimbingan as $kp)
                    <flux:table.row :key="$kp->id">
                        <flux:table.cell variant="strong">{{ $kp->mahasiswa->nama_mahasiswa }}</flux:table.cell>
                        <flux:table.cell>{{ $kp->mahasiswa->nim }}</flux:table.cell>
                        <flux:table.cell>{{ Str::limit($kp->judul_kp, 50) }}</flux:table.cell>
                        <flux:table.cell>
                            {{-- Tombol ini akan mengarah ke halaman detail bimbingan --}}
                            <flux:button as="a" href="{{ route('dospem.bimbingan.detail', $kp->id) }}" size="xs" variant="primary">
                                Lihat Logbook
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">Anda belum memiliki mahasiswa bimbingan.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="border-t p-4 dark:border-neutral-700">
            <Flux:pagination :pagination="$this->mahasiswaBimbingan"/>
        </div>
    </flux:card>
</div>
