<?php

use App\Imports\PenggunaImport;
use App\Models\Dosen;
use App\Models\Jurusan;
use App\Models\Mahasiswa;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Manajemen Pengguna')] #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $upload;

    public string $tab = 'mahasiswa';
    public bool $editing = false;
    public ?int $userId = null;

    // Properti untuk form tambah/edit pengguna
    public string $role = 'Mahasiswa';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public ?int $jurusan_id = null;
    // Mahasiswa fields
    public string $nim = '';
    public string $tahun_angkatan = '';
    // Dosen fields
    public string $nip = '';
    public bool $is_komisi = false;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function updatedTab()
    {
        $this->reset('search');
        $this->resetPage();
    }

    #[Computed]
    public function mahasiswas()
    {
        return Mahasiswa::with(['user', 'jurusan'])
            ->where(function ($query) {
                $query->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                    ->orWhere('nim', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', fn($q) => $q->where('email', 'like', '%' . $this->search . '%'));
            })
            ->orderBy('nama_mahasiswa')->paginate(10, ['*'], 'mahasiswaPage');
    }

    #[Computed]
    public function dosens()
    {
        return Dosen::with(['user', 'jurusan'])
            ->where(function ($query) {
                $query->where('nama_dosen', 'like', '%' . $this->search . '%')
                    ->orWhere('nip', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', fn($q) => $q->where('email', 'like', '%' . $this->search . '%'));
            })
            ->orderBy('nama_dosen')->paginate(10, ['*'], 'dosenPage');
    }

    #[Computed]
    public function jurusans()
    {
        return Jurusan::orderBy('nama_jurusan')->get();
    }

    public function add()
    {
        $this->resetForm();
        Flux::modal('user-modal')->show();
    }

    public function edit($profileId)
    {
        $this->resetForm();
        $this->editing = true;

        if ($this->tab === 'mahasiswa') {
            $profile = Mahasiswa::with('user')->findOrFail($profileId);
            $this->role = 'Mahasiswa';
            $this->nim = $profile->nim;
            $this->tahun_angkatan = $profile->tahun_angkatan;
        } else {
            $profile = Dosen::with('user')->findOrFail($profileId);
            $this->role = 'Dosen';
            $this->nip = $profile->nip;
            $this->is_komisi = $profile->is_komisi;
        }

        $this->userId = $profile->user_id;
        $this->jurusan_id = $profile->jurusan_id;
        $this->name = $profile->user->name;
        $this->email = $profile->user->email;

        Flux::modal('user-modal')->show();
    }

    public function save()
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->userId)],
            'role' => ['required', 'in:Mahasiswa,Dosen'],
            'jurusan_id' => ['required', 'exists:jurusans,id'],
        ];
        if (!$this->editing || !empty($this->password)) {
            $rules['password'] = ['required', 'string', Rules\Password::defaults()];
        }

        if ($this->role === 'Mahasiswa') {
            $mahasiswaIdToIgnore = $this->editing ? Mahasiswa::where('user_id', $this->userId)->value('id') : null;
            $rules['nim'] = ['required', 'string', 'max:255', Rule::unique(Mahasiswa::class)->ignore($mahasiswaIdToIgnore)];
            $rules['tahun_angkatan'] = ['required', 'digits:4', 'integer', 'min:1900'];
        } else {
            $dosenIdToIgnore = $this->editing ? Dosen::where('user_id', $this->userId)->value('id') : null;
            $rules['nip'] = ['required', 'string', 'max:255', Rule::unique(Dosen::class)->ignore($dosenIdToIgnore)];
            $rules['is_komisi'] = ['required', 'boolean'];
        }

        $messages = [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'jurusan_id.required' => 'Jurusan wajib dipilih.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.unique' => 'NIM ini sudah terdaftar.',
            'tahun_angkatan.required' => 'Tahun angkatan wajib diisi.',
            'nip.required' => 'NIP wajib diisi.',
            'nip.unique' => 'NIP ini sudah terdaftar.',
        ];

        $validated = $this->validate($rules, $messages);

        DB::transaction(function () use ($validated) {
            if ($this->editing) {
                $user = User::findOrFail($this->userId);
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
                if (!empty($validated['password'])) {
                    $user->update(['password' => Hash::make($validated['password'])]);
                }

                if ($this->role === 'Mahasiswa') {
                    $user->mahasiswa()->update([
                        'jurusan_id' => $validated['jurusan_id'],
                        'nama_mahasiswa' => $validated['name'],
                        'nim' => $validated['nim'],
                        'tahun_angkatan' => $validated['tahun_angkatan'],
                    ]);
                } else {
                    $user->dosen()->update([
                        'jurusan_id' => $validated['jurusan_id'],
                        'nama_dosen' => $validated['name'],
                        'nip' => $validated['nip'],
                        'is_komisi' => $validated['is_komisi'],
                    ]);
                }

            } else {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => $validated['role'] === 'Dosen' && $validated['is_komisi'] ? 'Dosen Komisi' : ($validated['role'] === 'Dosen' ? 'Dosen Pembimbing' : 'Mahasiswa'),
                ]);

                if ($validated['role'] === 'Mahasiswa') {
                    Mahasiswa::create([
                        'user_id' => $user->id,
                        'jurusan_id' => $validated['jurusan_id'],
                        'nama_mahasiswa' => $validated['name'],
                        'nim' => $validated['nim'],
                        'tahun_angkatan' => $validated['tahun_angkatan'],
                    ]);
                } else {
                    Dosen::create([
                        'user_id' => $user->id,
                        'jurusan_id' => $validated['jurusan_id'],
                        'nama_dosen' => $validated['name'],
                        'nip' => $validated['nip'],
                        'is_komisi' => $validated['is_komisi'],
                    ]);
                }
            }
        });

        Flux::modal('user-modal')->close();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: $this->editing ? 'Pengguna berhasil diperbarui.' : 'Pengguna baru berhasil ditambahkan.');
        $this->resetForm();
    }

    public function delete($profileId)
    {
        DB::transaction(function () use ($profileId) {
            if ($this->tab === 'mahasiswa') {
                $profile = Mahasiswa::findOrFail($profileId);
            } else {
                $profile = Dosen::findOrFail($profileId);
            }
            User::findOrFail($profile->user_id)->delete();
        });

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengguna telah dihapus.');
    }

    public function resetForm()
    {
        $this->reset(['editing', 'userId', 'name', 'email', 'password', 'jurusan_id', 'nim', 'tahun_angkatan', 'nip', 'is_komisi']);
        $this->role = 'Mahasiswa';
        $this->resetErrorBag();
    }

    public function import()
    {
        $this->validate([
            'upload' => 'required|file|mimes:xlsx,xls'
        ], [
            'upload.required' => 'Anda harus memilih file untuk diunggah.',
            'upload.mimes' => 'File harus dalam format .xlsx atau .xls.'
        ]);

        try {
            Excel::import(new PenggunaImport, $this->upload);
            Flux::modal('import-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data pengguna berhasil diimpor.');
            $this->upload = null;

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];

            foreach ($failures as $failure) {
                $errorMessages[] = 'Baris ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }
            Flux::toast(
                variant: 'danger',
                heading: 'Impor Gagal: Terdapat Kesalahan Data',
                text: 'Harap perbaiki error berikut di file Excel Anda: ' . implode('; ', $errorMessages)
            );
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'danger',
                heading: 'Terjadi Kesalahan',
                text: 'Impor gagal karena masalah teknis. Silakan coba lagi.'
            );
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Manajemen Data Pengguna</flux:heading>
        <flux:subheading size="lg">Kelola data untuk seluruh akun mahasiswa dan dosen dalam sistem.</flux:subheading>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <div class="lg:col-span-2 space-y-6">
            <flux:tab.group>
                <flux:tabs wire:model.live="tab">
                    <flux:tab name="mahasiswa" icon="academic-cap">Data Mahasiswa</flux:tab>
                    <flux:tab name="dosen" icon="user-circle">Data Dosen</flux:tab>
                </flux:tabs>
        
                <flux:tab.panel name="mahasiswa">
                    <flux:card>
                        <div class="p-4 border-b dark:border-neutral-700">
                             <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIM, atau email mahasiswa..." icon="magnifying-glass"/>
                        </div>
                        <flux:table :paginate="$this->mahasiswas">
                            <flux:table.columns>
                                <flux:table.column>Nama Mahasiswa</flux:table.column>
                                <flux:table.column>NIM</flux:table.column>
                                <flux:table.column>Jurusan</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($this->mahasiswas as $mahasiswa)
                                    <flux:table.row :key="$mahasiswa->id">
                                        <flux:table.cell variant="strong">
                                            {{ $mahasiswa->nama_mahasiswa }}
                                            <span class="block text-xs font-normal text-zinc-500">{{ $mahasiswa->user->email }}</span>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $mahasiswa->nim }}</flux:table.cell>
                                        <flux:table.cell>{{ $mahasiswa->jurusan->nama_jurusan }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex items-center gap-2">
                                                <flux:button size="xs" wire:click="edit({{ $mahasiswa->id }})">Edit</flux:button>
                                                <flux:modal.trigger :name="'delete-mahasiswa-' . $mahasiswa->id">
                                                    <flux:button size="xs" variant="danger">Hapus</flux:button>
                                                </flux:modal.trigger>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>

                                    <flux:modal :name="'delete-mahasiswa-' . $mahasiswa->id" class="md:w-96">
                                        <div class="space-y-6 text-center">
                                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                                <flux:icon name="trash" class="size-6 text-red-600"/>
                                            </div>
                                            <div>
                                                <flux:heading size="lg">Hapus Mahasiswa?</flux:heading>
                                                <flux:text class="mt-2">
                                                    Anda yakin ingin menghapus data mahasiswa
                                                    <span class="font-bold">{{ $mahasiswa->nama_mahasiswa }}</span>?
                                                    Semua data terkait (termasuk akun login) akan dihapus.
                                                </flux:text>
                                            </div>
                                            <div class="flex justify-center gap-3">
                                                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                                <flux:button variant="danger" wire:click="delete({{ $mahasiswa->id }})">Ya, Hapus</flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                            Data mahasiswa tidak ditemukan.
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
        
                <flux:tab.panel name="dosen">
                    <flux:card>
                        <div class="p-4 border-b dark:border-neutral-700">
                             <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIP, atau email dosen..." icon="magnifying-glass"/>
                        </div>
                        <flux:table :paginate="$this->dosens">
                            <flux:table.columns>
                                <flux:table.column>Nama Dosen</flux:table.column>
                                <flux:table.column>NIP</flux:table.column>
                                <flux:table.column>Status Komisi</flux:table.column>
                                <flux:table.column>Aksi</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @forelse ($this->dosens as $dosen)
                                    <flux:table.row :key="$dosen->id">
                                        <flux:table.cell variant="strong">
                                            {{ $dosen->nama_dosen }}
                                            <span class="block text-xs font-normal text-zinc-500">{{ $dosen->user->email }}</span>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $dosen->nip }}</flux:table.cell>
                                        <flux:table.cell>
                                            @if ($dosen->is_komisi)
                                                <flux:badge color="green" size="sm">Anggota Komisi</flux:badge>
                                            @else
                                                <span class="text-zinc-500">-</span>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex items-center gap-2">
                                                <flux:button size="xs" wire:click="edit({{ $dosen->id }})">Edit</flux:button>
                                                <flux:modal.trigger :name="'delete-dosen-' . $dosen->id">
                                                    <flux:button size="xs" variant="danger">Hapus</flux:button>
                                                </flux:modal.trigger>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>

                                    <flux:modal :name="'delete-dosen-' . $dosen->id" class="md:w-96">
                                        <div class="space-y-6 text-center">
                                            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                                <flux:icon name="trash" class="size-6 text-red-600"/>
                                            </div>
                                            <div>
                                                <flux:heading size="lg">Hapus Dosen?</flux:heading>
                                                <flux:text class="mt-2">
                                                    Anda yakin ingin menghapus data dosen
                                                    <span class="font-bold">{{ $dosen->nama_dosen }}</span>?
                                                    Semua data terkait (termasuk akun login) akan dihapus.
                                                </flux:text>
                                            </div>
                                            <div class="flex justify-center gap-3">
                                                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                                                <flux:button variant="danger" wire:click="delete({{ $dosen->id }})">Ya, Hapus</flux:button>
                                            </div>
                                        </div>
                                    </flux:modal>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="4" class="text-center py-12 text-zinc-500">
                                            Data dosen tidak ditemukan.
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
        
        <div class="lg:col-span-1 space-y-8">
            <flux:card>
                <h3 class="text-lg font-semibold mb-2">Manajemen Cepat</h3>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">Gunakan tombol di bawah untuk menambah pengguna secara manual atau massal.</p>
                <div class="flex flex-col space-y-2">
                    <flux:modal.trigger name="user-modal">
                        <flux:button variant="primary" icon="plus" class="w-full justify-center" @click="$wire.add()">Tambah Pengguna Manual</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="import-modal">
                        {{-- [FIX] Mengganti variant="secondary" menjadi "ghost" --}}
                        <flux:button variant="ghost" icon="document-arrow-up" class="w-full justify-center">Impor dari Excel</flux:button>
                    </flux:modal.trigger>
                    <flux:button as="a" href="{{ route('master.pengguna.template') }}" variant="ghost" icon="document-arrow-down" class="w-full justify-center">Unduh Template</flux:button>
                </div>
            </flux:card>
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Informasi Peran</h3>
                <div class="space-y-4 text-sm">
                    <div>
                        <p class="font-semibold">Mahasiswa</p>
                        <p class="text-zinc-600 dark:text-zinc-400">Akun untuk mahasiswa yang akan melaksanakan Kerja Praktik.</p>
                    </div>
                    <div>
                        <p class="font-semibold">Dosen Pembimbing</p>
                        <p class="text-zinc-600 dark:text-zinc-400">Akun untuk dosen yang akan menjadi pembimbing KP.</p>
                    </div>
                    <div>
                        <p class="font-semibold">Dosen Komisi</p>
                        <p class="text-zinc-600 dark:text-zinc-400">Akun Dosen yang juga memiliki hak untuk memvalidasi proposal KP. Aktifkan toggle "Anggota Komisi" saat menambah/mengedit data dosen.</p>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
    
    <flux:modal name="user-modal" class="md:w-[32rem]">
        <form wire:submit="save">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editing ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}</flux:heading>
                </div>
                <div class="space-y-4">
                    <flux:radio.group wire:model.live="role" label="Peran">
                        <flux:radio value="Mahasiswa" label="Mahasiswa" :disabled="$editing"/>
                        <flux:radio value="Dosen" label="Dosen" :disabled="$editing"/>
                    </flux:radio.group>
                    <flux:input wire:model="name" label="Nama Lengkap" required/>
                    <flux:input wire:model="email" type="email" label="Alamat Email" required/>
                    <flux:input wire:model="password" type="password" label="Password" :placeholder="$editing ? 'Kosongkan jika tidak ingin diubah' : ''" :required="!$editing" viewable/>
                    <flux:select wire:model="jurusan_id" label="Jurusan" required>
                        <option value="">Pilih Jurusan</option>
                        @foreach($this->jurusans as $jurusan)
                            <option value="{{ $jurusan->id }}">{{ $jurusan->nama_jurusan }}</option>
                        @endforeach
                    </flux:select>
                    @if ($role === 'Mahasiswa')
                        <div class="space-y-4 rounded-lg border bg-sky-50 p-4 dark:border-sky-900/50 dark:bg-sky-900/20">
                            <flux:input wire:model="nim" label="NIM" required/>
                            <flux:input wire:model="tahun_angkatan" label="Tahun Angkatan" type="number" required/>
                        </div>
                    @endif
                    @if ($role === 'Dosen')
                        <div class="space-y-4 rounded-lg border bg-teal-50 p-4 dark:border-teal-900/50 dark:bg-teal-900/20">
                            <flux:input wire:model="nip" label="NIP" required/>
                            <flux:switch wire:model="is_komisi" label="Anggota Komisi KP?"/>
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="import-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Impor Data Pengguna</flux:heading>
                <flux:text class="mt-2">Unggah file Excel (.xlsx) sesuai template untuk menambah banyak pengguna sekaligus.</flux:text>
            </div>
            <form wire:submit="import" enctype="multipart/form-data" class="space-y-4">
                <flux:input wire:model="upload" type="file" label="File Excel" required />
                @error('upload') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="import">Impor</span>
                        <span wire:loading wire:target="import">Mengimpor...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>