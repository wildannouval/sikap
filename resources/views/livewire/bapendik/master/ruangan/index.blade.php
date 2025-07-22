<?php

use App\Models\Ruangan;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Ruangan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti untuk form di modal
    public bool $editing = false;
    public ?int $ruanganId = null;
    public string $nama_ruangan = '';
    public string $lokasi_gedung = '';

    #[Computed]
    public function ruangans()
    {
        return Ruangan::orderBy('nama_ruangan')->paginate(10);
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
    }

    public function delete($id)
    {
        Ruangan::findOrFail($id)->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data ruangan telah dihapus.');
    }

    public function resetForm()
    {
        $this->reset(['editing', 'ruanganId', 'nama_ruangan', 'lokasi_gedung']);
    }
}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Manajemen Data Ruangan</flux:heading>
            <flux:subheading size="lg" class="mb-6">Kelola semua data ruangan yang tersedia untuk seminar.
            </flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" icon="plus" wire:click="add">Tambah Ruangan</flux:button>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    {{-- Tabel Daftar Ruangan --}}
    <flux:card class="mt-8">
        <flux:table>
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

                    {{-- Modal Konfirmasi Hapus --}}
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
                        <flux:table.cell colspan="3" class="text-center text-neutral-500">
                            Data ruangan tidak ditemukan.
                        </flux:table.cell>
                    </flux:table.row>
                        @endforelse
                    </flux:table.rows>
            </flux:table>
            <div class="border-t p-4 dark:border-neutral-700">
                <flux:pagination :paginator="$this->ruangans"/>
            </div>
        </flux:card>

        {{-- Modal untuk Tambah/Edit Ruangan --}}
        <flux:modal name="ruangan-modal" class="md:w-[32rem]">
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
                    <flux:button wire:click="save" variant="primary">Simpan</flux:button>
                </div>
            </div>
        </flux:modal>
</div>
