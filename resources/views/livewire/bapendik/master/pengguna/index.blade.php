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

    // Properti untuk mengontrol tab yang aktif. Defaultnya 'mahasiswa'.
    public string $tab = 'mahasiswa';
    public bool $editing = false; // <-- TAMBAHKAN INI
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

    #[Computed]
    public function mahasiswas()
    {
        // Modifikasi query ini
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
        // Modifikasi query ini
        return Dosen::with(['user', 'jurusan'])
            ->where(function ($query) {
                $query->where('nama_dosen', 'like', '%' . $this->search . '%')
                    ->orWhere('nip', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', fn($q) => $q->where('email', 'like', '%' . $this->search . '%'));
            })
            ->orderBy('nama_dosen')->paginate(10, ['*'], 'dosenPage');
    }

    /**
     * Mengambil semua data jurusan untuk dropdown.
     */
    #[Computed]
    public function jurusans()
    {
        return Jurusan::orderBy('nama_jurusan')->get();
    }

    /**
     * Menyiapkan dan membuka modal untuk menambah pengguna baru.
     */
    public function add()
    {
        $this->resetForm();
        Flux::modal('user-modal')->show();
    }

    /**
     * Menyiapkan dan membuka modal untuk mengedit pengguna.
     */
    public function edit($profileId)
    {
        $this->resetForm();
        $this->editing = true;

        if ($this->tab === 'mahasiswa') {
            $profile = Mahasiswa::with('user')->findOrFail($profileId);
            $this->role = 'Mahasiswa';
            $this->nim = $profile->nim;
            $this->tahun_angkatan = $profile->tahun_angkatan;
        } else { // dosen
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

    /**
     * Menyimpan pengguna baru (Mahasiswa atau Dosen).
     */
    public function save()
    {
        // Inisialisasi array rules
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->userId)],
            'role' => ['required', 'in:Mahasiswa,Dosen'],
            'jurusan_id' => ['required', 'exists:jurusans,id'],
        ];
        if (!$this->editing || !empty($this->password)) {
            $rules['password'] = ['required', 'string', Rules\Password::defaults()];
        }

        // Validasi password
//        if (!$this->editing) {
//            $rules['password'] = ['required', 'string', Rules\Password::defaults()];
//        } elseif (!empty($this->password)) {
//            $rules['password'] = ['string', Rules\Password::defaults()];
//        }

        // Validasi tambahan berdasarkan role
        if ($this->role === 'Mahasiswa') {
            $mahasiswaIdToIgnore = $this->editing ? Mahasiswa::where('user_id', $this->userId)->value('id') : null;
            $rules['nim'] = ['required', 'string', 'max:255', Rule::unique(Mahasiswa::class)->ignore($mahasiswaIdToIgnore)];
            $rules['tahun_angkatan'] = ['required', 'digits:4', 'integer', 'min:1900'];
        } else {
            $dosenIdToIgnore = $this->editing ? Dosen::where('user_id', $this->userId)->value('id') : null;
            $rules['nip'] = ['required', 'string', 'max:255', Rule::unique(Dosen::class)->ignore($dosenIdToIgnore)];
            $rules['is_komisi'] = ['required', 'boolean'];
        }
//        if ($this->role === 'Mahasiswa') {
//            $rules['nim'] = ['required', 'string', 'max:255', Rule::unique(Mahasiswa::class)->ignore($this->editing ? $this->userId : null, 'user_id')];
//            $rules['tahun_angkatan'] = ['required', 'digits:4', 'integer', 'min:1900'];
//        } else {
//            $rules['nip'] = ['required', 'string', 'max:255', Rule::unique(Dosen::class)->ignore($this->editing ? $this->userId : null, 'user_id')];
//            $rules['is_komisi'] = ['required', 'boolean'];
//        }

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

        // Jalankan validasi dengan aturan yang sudah dibentuk
        $validated = $this->validate($rules, $messages);

        // Gunakan transaksi database untuk memastikan kedua tabel berhasil diisi
        DB::transaction(function () use ($validated) {
            if ($this->editing) {
                $user = User::findOrFail($this->userId);
                $user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
                // Hanya update password jika diisi
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
                } else { // Dosen
                    $user->dosen()->update([
                        'jurusan_id' => $validated['jurusan_id'],
                        'nama_dosen' => $validated['name'],
                        'nip' => $validated['nip'],
                        'is_komisi' => $validated['is_komisi'],
                    ]);
                }

            } else {
                // 1. Buat data di tabel User
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => $validated['role'] === 'Dosen' && $validated['is_komisi'] ? 'Dosen Komisi' : ($validated['role'] === 'Dosen' ? 'Dosen Pembimbing' : 'Mahasiswa'),
                ]);

                // 2. Buat data di tabel Mahasiswa atau Dosen
                if ($validated['role'] === 'Mahasiswa') {
                    Mahasiswa::create([
                        'user_id' => $user->id,
                        'jurusan_id' => $validated['jurusan_id'],
                        'nama_mahasiswa' => $validated['name'],
                        'nim' => $validated['nim'],
                        'tahun_angkatan' => $validated['tahun_angkatan'],
                    ]);
                } else { // Dosen
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

    /**
     * Menghapus data pengguna (User dan Profilnya).
     */
    public function delete($profileId)
    {
        DB::transaction(function () use ($profileId) {
            if ($this->tab === 'mahasiswa') {
                $profile = Mahasiswa::findOrFail($profileId);
            } else { // dosen
                $profile = Dosen::findOrFail($profileId);
            }
            // Hapus data User, dan data profil akan terhapus otomatis karena onDelete('cascade')
            User::findOrFail($profile->user_id)->delete();
        });

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Pengguna telah dihapus.');
    }

    public function resetForm()
    {
        $this->reset(['editing', 'userId', 'name', 'email', 'password', 'jurusan_id', 'nim', 'tahun_angkatan', 'nip', 'is_komisi']);
//        $this->reset([
//            'name', 'email', 'password', 'role', 'jurusan_id',
//            'nim', 'tahun_angkatan', 'nip', 'is_komisi',
//            'editing', 'userId' // <- reset juga properti ini
//        ]);
        $this->role = 'Mahasiswa';
//        $this->editing = false;
//        $this->userId = null;
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
            // Coba jalankan proses impor
            Excel::import(new PenggunaImport, $this->upload);

            // Jika berhasil, tutup modal dan tampilkan pesan sukses
            Flux::modal('import-modal')->close();
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data pengguna berhasil diimpor.');
            $this->upload = null;

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Jika terjadi error validasi dari library Excel
            $failures = $e->failures();
            $errorMessages = [];

            foreach ($failures as $failure) {
                // Kumpulkan semua pesan error dari setiap baris yang gagal
                $errorMessages[] = 'Baris ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }

            // Tampilkan notifikasi toast yang berisi detail error
            Flux::toast(
                variant: 'danger',
                heading: 'Impor Gagal: Terdapat Kesalahan Data',
                text: 'Harap perbaiki error berikut di file Excel Anda: ' . implode('; ', $errorMessages)
            );
        } catch (\Exception $e) {
            // Tangkap error umum lainnya
            Flux::toast(
                variant: 'danger',
                heading: 'Terjadi Kesalahan',
                text: 'Impor gagal karena masalah teknis. Silakan coba lagi.'
            );
        }
    }


}; ?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Manajemen Data Pengguna</flux:heading>
            <flux:subheading size="lg" class="mb-6">Kelola data pengguna mahasiswa dan dosen.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button as="a" href="{{ route('master.pengguna.template') }}" variant="primary" icon="document-arrow-down">Unduh Template</flux:button>
            <flux:modal.trigger name="import-modal">
                <flux:button variant="primary" icon="document-arrow-up">Impor Pengguna</flux:button>
            </flux:modal.trigger>
            <flux:separator vertical class="my-2" />
            <flux:modal.trigger name="user-modal">
                <flux:button variant="primary" icon="plus">Tambah Pengguna</flux:button>
            </flux:modal.trigger>
        </div>
{{--        <div>--}}
{{--            <flux:modal.trigger name="user-modal">--}}
{{--                <flux:button variant="primary" icon="plus">Tambah Pengguna</flux:button>--}}
{{--            </flux:modal.trigger>--}}
{{--        </div>--}}
    </div>
    <flux:separator/>

    <div class="mt-6">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari berdasarkan nama, NIM/NIP, atau email..."
                    icon="magnifying-glass"/>
    </div>

    {{-- Grup Tab --}}
    <flux:tab.group class="mt-4">
        {{-- Daftar Tab --}}
        <flux:tabs wire:model.live="tab">
            <flux:tab name="mahasiswa" icon="academic-cap">Data Mahasiswa</flux:tab>
            <flux:tab name="dosen" icon="user-circle">Data Dosen</flux:tab>
        </flux:tabs>

        {{-- Panel untuk Tab Mahasiswa --}}
        <flux:tab.panel name="mahasiswa">
            <flux:card class="mt-4">
                <flux:table :paginate="$this->mahasiswas">
                    <flux:table.columns>
                        <flux:table.column>Nama Mahasiswa</flux:table.column>
                        <flux:table.column>NIM</flux:table.column>
                        <flux:table.column>Jurusan</flux:table.column>
                        <flux:table.column>Email</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->mahasiswas as $mahasiswa)
                            <flux:table.row :key="$mahasiswa->id">
                                <flux:table.cell variant="strong">{{ $mahasiswa->nama_mahasiswa }}</flux:table.cell>
                                <flux:table.cell>{{ $mahasiswa->nim }}</flux:table.cell>
                                <flux:table.cell>{{ $mahasiswa->jurusan->nama_jurusan }}</flux:table.cell>
                                <flux:table.cell>{{ $mahasiswa->user->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button size="xs" wire:click="edit({{ $mahasiswa->id }})">Edit
                                        </flux:button>
                                        <flux:modal.trigger :name="'delete-mahasiswa-' . $mahasiswa->id">
                                            <flux:button size="xs" variant="danger">Hapus</flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>

                            <flux:modal :name="'delete-mahasiswa-' . $mahasiswa->id" class="md:w-96">
                                <div class="space-y-6 text-center">
                                    <div
                                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                        <flux:icon name="trash" class="size-6 text-red-600"/>
                                    </div>
                                    <div>
                                        <flux:heading size="lg">Hapus Mahasiswa?</flux:heading>
                                        <flux:text class="mt-2">
                                            Anda yakin ingin menghapus data mahasiswa
                                            <span class="font-bold">{{ $mahasiswa->nama_mahasiswa }}</span>?
                                            Semua data terkait (termasuk akun login) akan dihapus secara permanen.
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">Batal</flux:button>
                                        </flux:modal.close>
                                        <flux:button variant="danger" wire:click="delete({{ $mahasiswa->id }})">
                                            Ya, Hapus
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>

                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center">Data mahasiswa tidak ditemukan.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                {{--                <div class="border-t p-4 dark:border-neutral-700">--}}
                {{--                    <flux:pagination :paginator="$this->mahasiswas"/>--}}
                {{--                </div>--}}
            </flux:card>
        </flux:tab.panel>

        {{-- Panel untuk Tab Dosen --}}
        <flux:tab.panel name="dosen">
            <flux:card class="mt-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nama Dosen</flux:table.column>
                        <flux:table.column>NIP</flux:table.column>
                        <flux:table.column>Jurusan</flux:table.column>
                        <flux:table.column>Email</flux:table.column>
                        <flux:table.column>Status Komisi</flux:table.column>
                        <flux:table.column>Aksi</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->dosens as $dosen)
                            <flux:table.row :key="$dosen->id">
                                <flux:table.cell variant="strong">{{ $dosen->nama_dosen }}</flux:table.cell>
                                <flux:table.cell>{{ $dosen->nip }}</flux:table.cell>
                                <flux:table.cell>{{ $dosen->jurusan->nama_jurusan }}</flux:table.cell>
                                <flux:table.cell>{{ $dosen->user->email }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($dosen->is_komisi)
                                        <flux:badge color="green" size="sm">Ya</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Tidak</flux:badge>
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

                            {{-- Modal Konfirmasi Hapus Dosen --}}
                            <flux:modal :name="'delete-dosen-' . $dosen->id" class="md:w-96">
                                <div class="space-y-6 text-center">
                                    <div
                                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                                        <flux:icon name="trash" class="size-6 text-red-600"/>
                                    </div>
                                    <div>
                                        <flux:heading size="lg">Hapus Dosen?</flux:heading>
                                        <flux:text class="mt-2">
                                            Anda yakin ingin menghapus data dosen
                                            <span class="font-bold">{{ $dosen->nama_dosen }}</span>?
                                            Semua data terkait (termasuk akun login) akan dihapus secara permanen.
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-center gap-3">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">Batal</flux:button>
                                        </flux:modal.close>
                                        <flux:button variant="danger" wire:click="delete({{ $dosen->id }})">
                                            Ya, Hapus
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center">Data dosen tidak ditemukan.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t p-4 dark:border-neutral-700">
                    <flux:pagination :paginator="$this->dosens"/>
                </div>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:modal name="user-modal" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editing ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}</flux:heading>
            </div>
            <div class="space-y-4">
                {{-- Pilihan Peran --}}
                <flux:radio.group wire:model.live="role" label="Peran">
                    <flux:radio value="Mahasiswa" label="Mahasiswa" :disabled="$editing"/>
                    <flux:radio value="Dosen" label="Dosen" :disabled="$editing"/>
                </flux:radio.group>

                {{-- Form Umum --}}
                <flux:input wire:model="name" label="Nama Lengkap" required/>
                {{--                @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}

                <flux:input wire:model="email" type="email" label="Alamat Email" required/>
                {{--                @error('email') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}

                <flux:input wire:model="password" type="password" label="Password"
                            :placeholder="$editing ? 'Kosongkan jika tidak ingin diubah' : ''" :required="!$editing"
                            viewable/>
                {{--                @error('password') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}

                <flux:select wire:model="jurusan_id" label="Jurusan" required>
                    <option value="">Pilih Jurusan</option>
                    @foreach($this->jurusans as $jurusan)
                        <option value="{{ $jurusan->id }}">{{ $jurusan->nama_jurusan }}</option>
                    @endforeach
                </flux:select>
                {{--                @error('jurusan_id') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}

                {{-- Form Khusus Mahasiswa --}}
                @if ($role === 'Mahasiswa')
                    <div class="space-y-4 rounded-lg border bg-sky-50 p-4 dark:border-sky-900/50 dark:bg-sky-900/20">
                        <flux:input wire:model="nim" label="NIM" required/>
                        {{--                        @error('nim') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}

                        <flux:input wire:model="tahun_angkatan" label="Tahun Angkatan" type="number" required/>
                        {{--                        @error('tahun_angkatan') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}
                    </div>
                @endif

                {{-- Form Khusus Dosen --}}
                @if ($role === 'Dosen')
                    <div class="space-y-4 rounded-lg border bg-teal-50 p-4 dark:border-teal-900/50 dark:bg-teal-900/20">
                        <flux:input wire:model="nip" label="NIP" required/>
                        {{--                        @error('nip') <span class="text-sm text-red-500">{{ $message }}</span> @enderror--}}

                        <flux:switch wire:model="is_komisi" label="Apakah anggota komisi?"/>
                    </div>
                @endif
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="save" variant="primary">Simpan</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal untuk Impor Pengguna --}}
    <flux:modal name="import-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Impor Data Pengguna</flux:heading>
                <flux:text class="mt-2">Unggah file Excel (.xlsx) sesuai template untuk menambah banyak pengguna sekaligus.</flux:text>
            </div>

            {{-- PERBAIKAN: Tambahkan enctype pada form --}}
            <form wire:submit="import" enctype="multipart/form-data" class="space-y-4">
                <flux:input wire:model="upload" type="file" label="File Excel" required />
                @error('upload') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3">
                    <flux:modal.close><flux:button type="button" variant="ghost">Batal</flux:button></flux:modal.close>

                    {{-- PERBAIKAN: Tambahkan loading state pada tombol --}}
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="import">Impor</span>
                        <span wire:loading wire:target="import">Mengimpor...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
