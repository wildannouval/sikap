<?php

use App\Models\Ruangan;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Ruangan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti untuk form di modal
    public bool $editing = false;
    public ?int $ruanganId = null;
    public string $nama_ruangan = '';
    public string $lokasi_gedung = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function ruangans()
    {
        return Ruangan::where('nama_ruangan', 'like', '%' . $this->search . '%')
            ->orWhere('lokasi_gedung', 'like', '%' . $this->search . '%')
            ->orderBy('nama_ruangan')
            ->paginate(10);
    }

    public function add()
    {
        $this->resetForm();
        Flux::modal('ruangan-modal')->show();
    }

    public function edit($id)
    {
        $ruangan = Ruangan::findOrFail($id);
        $this->editing = true;
        $this->ruanganId = $ruangan->id;
        $this->nama_ruangan = $ruangan->nama_ruangan;
        $this->lokasi_gedung = $ruangan->lokasi_gedung;
        Flux::modal('ruangan-modal')->show();
    }

    public function save()
    {
        $validated = $this->validate([
            'nama_ruangan' => 'required|string|max:255',
            'lokasi_gedung' => 'required|string|max:255',
        ]);

        if ($this->editing) {
            Ruangan::findOrFail($this->ruanganId)->update($validated);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data ruangan berhasil diperbarui.');
        } else {
            Ruangan::create($validated);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Ruangan baru berhasil ditambahkan.');
        }
        Flux::modal('ruangan-modal')->close();
        $this->resetForm();
    }

    public function delete($id)
    {
        Ruangan::findOrFail($id)->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data ruangan telah dihapus.');
    }

    public function resetForm()
    {
        $this->reset(['editing', 'ruanganId', 'nama_ruangan', 'lokasi_gedung']);
        $this->resetErrorBag();
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Manajemen Data Ruangan</flux:heading>
        <flux:subheading size="lg">Kelola semua data ruangan yang tersedia untuk seminar.</flux:subheading>
    </div>

    {{-- [START] PERUBAHAN LAYOUT MENJADI DUA KOLOM --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        {{-- Kolom Kiri (Utama): Tabel dan Aksi --}}
        <div class="lg:col-span-2 space-y-6">
             <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama ruangan atau lokasi gedung..." icon="magnifying-glass" />
                </div>
                <div class="flex-shrink-0">
                    <flux:button variant="primary" icon="plus" wire:click="add" class="w-full sm:w-auto">Tambah Ruangan</flux:button>
                </div>
            </div>
            
            <flux:card>
                <flux:table :paginate="$this->ruangans">
                    <flux:table.columns>
                        <flux:table.column>Nama Ruangan</flux:table.column>
                        <flux:table.column>Lokasi Gedung</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->ruangans as $ruangan)
                            <flux:table.row :key="$ruangan->id">
                                <flux:table.cell variant="strong">{{ $ruangan->nama_ruangan }}</flux:table.cell>
                                <flux:table.cell>{{ $ruangan->lokasi_gedung }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button size="xs" wire:click="edit({{ $ruangan->id }})">Edit</flux:button>
                                        <flux:modal.trigger :name="'delete-ruangan-' . $ruangan->id">
                                            <flux:button size="xs" variant="danger">Hapus</flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>

                            <flux:modal :name="'delete-ruangan-' . $ruangan->id" class="md:w-96">
                                <div class="space-y-6 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                        <flux:icon name="trash" class="size-6 text-red-600"/>
                                    </div>
                                    <div>
                                        <flux:heading size="lg">Hapus Ruangan?</flux:heading>
                                        <flux:text class="mt-2">
                                            Anda yakin ingin menghapus ruangan <span
                                                class="font-bold">{{ $ruangan->nama_ruangan }}</span>?
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">Batal</flux:button>
                                        </flux:modal.close>
                                        <flux:button variant="danger" wire:click="delete({{ $ruangan->id }})">Ya, Hapus
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center py-12 text-zinc-500">
                                    Data ruangan tidak ditemukan.
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
                <h3 class="text-lg font-semibold mb-4">Informasi Pengelolaan</h3>
                <div class="space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <p>Halaman ini digunakan untuk mengelola daftar ruangan yang dapat dipilih oleh mahasiswa dan Bapendik saat proses pendaftaran atau penjadwalan seminar.</p>
                    <ol class="list-decimal list-inside space-y-2 pl-1">
                        <li>Gunakan tombol <b>"Tambah Ruangan"</b> untuk menambahkan data ruangan baru.</li>
                        <li>Pastikan <b>Nama Ruangan</b> dan <b>Lokasi Gedung</b> diisi dengan jelas.</li>
                        <li>Data yang ditambahkan di sini akan otomatis muncul sebagai pilihan di form pendaftaran seminar.</li>
                        <li>Gunakan tombol <b>"Edit"</b> atau <b>"Hapus"</b> pada setiap baris untuk memperbarui atau menghapus data.</li>
                    </ol>
                </div>
            </flux:card>
        </div>
    </div>
    {{-- [END] PERUBAHAN LAYOUT --}}

    {{-- Modal untuk Tambah/Edit Ruangan --}}
    <flux:modal name="ruangan-modal" class="md:w-[32rem]">
        <form wire:submit="save">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editing ? 'Edit Ruangan' : 'Tambah Ruangan Baru' }}</flux:heading>
                </div>
                <div class="space-y-4">
                    <flux:input wire:model="nama_ruangan" label="Nama Ruangan" required/>
                    @error('nama_ruangan') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    <flux:input wire:model="lokasi_gedung" label="Lokasi Gedung" required/>
                    @error('lokasi_gedung') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>