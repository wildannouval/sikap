<?php

use App\Models\Jurusan;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Jurusan')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Properti untuk form di modal
    public bool $editing = false;
    public ?int $jurusanId = null;
    public string $kode_jurusan = '';
    public string $nama_jurusan = '';


    #[Computed]
    public function jurusans()
    {
        return Jurusan::orderBy('nama_jurusan')->paginate(10);
    }

    /**
     * Menyiapkan dan membuka modal untuk menambah jurusan baru.
     */
    public function add()
    {
        $this->resetForm();
        Flux::modal('jurusan-modal')->show();
    }

    /**
     * Menyiapkan dan membuka modal untuk mengedit jurusan.
     */
    public function edit($id)
    {
        $jurusan = Jurusan::findOrFail($id);
        $this->editing = true;
        $this->jurusanId = $jurusan->id;
        $this->kode_jurusan = $jurusan->kode_jurusan;
        $this->nama_jurusan = $jurusan->nama_jurusan;

        Flux::modal('jurusan-modal')->show();
    }

    /**
     * Menyimpan data (baik membuat baru atau memperbarui).
     */
    public function save()
    {
        $rules = [
            'kode_jurusan' => 'required|string|max:10|unique:jurusans,kode_jurusan,' . $this->jurusanId,
            'nama_jurusan' => 'required|string|max:255',
        ];

        $validated = $this->validate($rules);

        if ($this->editing) {
            // Update data yang ada
            Jurusan::findOrFail($this->jurusanId)->update($validated);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data jurusan berhasil diperbarui.');
        } else {
            // Buat data baru
            Jurusan::create($validated);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Jurusan baru berhasil ditambahkan.');
        }

        $this->reset();
        Flux::modal('jurusan-modal')->close();

    }

    /**
     * Menghapus data jurusan.
     */
    public function delete($id)
    {
        // Pengecekan relasi bisa ditambahkan di sini jika diperlukan
        Jurusan::findOrFail($id)->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data jurusan telah dihapus.');
    }

    /**
     * Mereset properti form.
     */
    public function resetForm()
    {
        $this->reset(['editing', 'jurusanId', 'kode_jurusan', 'nama_jurusan']);
    }

}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Manajemen Data Jurusan</flux:heading>
            <flux:subheading size="lg" class="mb-6">Kelola semua data jurusan yang tersedia di sistem.</flux:subheading>
        </div>
        <div>
            {{-- Tombol ini sekarang memanggil fungsi add() --}}
            <flux:button variant="primary" icon="plus" wire:click="add">Tambah Jurusan</flux:button>
        </div>
    </div>
    <flux:separator variant="subtle"/>

    {{-- Tabel Daftar Jurusan --}}
    <flux:card class="mt-8">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kode Jurusan</flux:table.column>
                <flux:table.column>Nama Jurusan</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->jurusans as $jurusan)
                    <flux:table.row :key="$jurusan->id">
                        <flux:table.cell variant="strong">{{ $jurusan->kode_jurusan }}</flux:table.cell>
                        <flux:table.cell>{{ $jurusan->nama_jurusan }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                {{-- Tombol Edit dan Hapus sekarang fungsional --}}
                                <flux:button size="xs" wire:click="edit({{ $jurusan->id }})">Edit</flux:button>
                                <flux:modal.trigger :name="'delete-jurusan-' . $jurusan->id">
                                    <flux:button size="xs" variant="danger">Hapus</flux:button>
                                </flux:modal.trigger>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>

                    {{-- Modal Konfirmasi Hapus --}}
                    <flux:modal :name="'delete-jurusan-' . $jurusan->id" class="md:w-96">
                        <div class="space-y-6 text-center">
                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                <flux:icon name="trash" class="size-6 text-red-600"/>
                            </div>
                            <div>
                                <flux:heading size="lg">Hapus Jurusan?</flux:heading>
                                <flux:text class="mt-2">
                                    Anda yakin ingin menghapus jurusan <span class="font-bold">{{ $jurusan->nama_jurusan }}</span>?
                                </flux:text>
                            </div>
                            <div class="flex justify-center gap-3">
                                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                <flux:button variant="danger" wire:click="delete({{ $jurusan->id }})">Ya, Hapus</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-neutral-500">
                            Data jurusan tidak ditemukan.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="border-t p-4 dark:border-neutral-700">
            <flux:pagination :paginator="$this->jurusans"/>
        </div>
    </flux:card>

    {{-- Modal untuk Tambah/Edit Jurusan --}}
    <flux:modal name="jurusan-modal" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editing ? 'Edit Jurusan' : 'Tambah Jurusan Baru' }}</flux:heading>
            </div>
            <div class="space-y-4">
                <flux:input wire:model="kode_jurusan" label="Kode Jurusan" required />
                @error('kode_jurusan') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

                <flux:input wire:model="nama_jurusan" label="Nama Jurusan" required />
                @error('nama_jurusan') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button wire:click="save" variant="primary">Simpan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
