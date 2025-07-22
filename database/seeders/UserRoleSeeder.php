<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Jurusan;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gunakan transaction untuk memastikan semua data berhasil dibuat
        DB::transaction(function () {
            // 1. Buat Jurusan sebagai contoh
            $jurusan = Jurusan::create([
                'kode_jurusan' => 'IF',
                'nama_jurusan' => 'Teknik Informatika',
            ]);

            // 2. Buat Akun Mahasiswa
            $mahasiswaUser = User::create([
                'name' => 'Mahasiswa Contoh',
                'email' => 'mahasiswa@sikap.test',
                'password' => Hash::make('password'),
                'role' => 'Mahasiswa',
            ]);

            Mahasiswa::create([
                'user_id' => $mahasiswaUser->id,
                'jurusan_id' => $jurusan->id,
                'nama_mahasiswa' => $mahasiswaUser->name,
                'nim' => 'G1A021001',
                'tahun_angkatan' => '2021',
            ]);

            // 3. Buat Akun Bapendik
            User::create([
                'name' => 'Staf Bapendik',
                'email' => 'bapendik@sikap.test',
                'password' => Hash::make('password'),
                'role' => 'Bapendik',
            ]);

            // 4. Buat Akun Dosen Pembimbing
            $dospemUser = User::create([
                'name' => 'Dosen Pembimbing Contoh',
                'email' => 'dospem@sikap.test',
                'password' => Hash::make('password'),
                'role' => 'Dosen Pembimbing',
            ]);

            Dosen::create([
                'user_id' => $dospemUser->id,
                'jurusan_id' => $jurusan->id,
                'nama_dosen' => $dospemUser->name,
                'nip' => '198001012005011001',
                'is_komisi' => false,
            ]);

            // 5. Buat Akun Dosen Komisi
            $doskomUser = User::create([
                'name' => 'Dosen Komisi Contoh',
                'email' => 'doskom@sikap.test',
                'password' => Hash::make('password'),
                'role' => 'Dosen Komisi',
            ]);

            Dosen::create([
                'user_id' => $doskomUser->id,
                'jurusan_id' => $jurusan->id,
                'nama_dosen' => $doskomUser->name,
                'nip' => '198202022006021002',
                'is_komisi' => true,
            ]);
        });
    }
}
