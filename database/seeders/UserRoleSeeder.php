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
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            // Jurusan IF (aman bila dijalankan berulang)
            Jurusan::upsert([
                ['kode_jurusan' => 'IF', 'nama_jurusan' => 'Teknik Informatika', 'created_at' => $now, 'updated_at' => $now],
            ], ['kode_jurusan'], ['nama_jurusan', 'updated_at']);

            $jurusan = Jurusan::where('kode_jurusan', 'IF')->first();

            // Mahasiswa contoh
            $mahasiswaUser = User::updateOrCreate(
                ['email' => 'mahasiswa@sikap.test'],
                [
                    'name' => 'Mahasiswa Contoh',
                    'password' => Hash::make('password'),
                    'role' => 'Mahasiswa',
                    'email_verified_at' => $now,
                ]
            );

            // ⬇️ gunakan kolom `tahun_angkatan` (bukan `angkatan`)
            Mahasiswa::updateOrCreate(
                ['user_id' => $mahasiswaUser->id],
                [
                    'jurusan_id'      => $jurusan->id,
                    'nama_mahasiswa'  => $mahasiswaUser->name,
                    'nim'             => 'G1A021001',
                    'tahun_angkatan'  => 2021,
                ]
            );

            // Bapendik
            User::updateOrCreate(
                ['email' => 'bapendik@sikap.test'],
                [
                    'name' => 'Staf Bapendik',
                    'password' => Hash::make('password'),
                    'role' => 'Bapendik',
                    'email_verified_at' => $now,
                ]
            );

            // Dosen Pembimbing
            $dospemUser = User::updateOrCreate(
                ['email' => 'dospem@sikap.test'],
                [
                    'name' => 'Dosen Pembimbing Contoh',
                    'password' => Hash::make('password'),
                    'role' => 'Dosen Pembimbing',
                    'email_verified_at' => $now,
                ]
            );
            Dosen::updateOrCreate(
                ['user_id' => $dospemUser->id],
                [
                    'jurusan_id' => $jurusan->id,
                    'nama_dosen' => $dospemUser->name,
                    'nip'        => '198001012005011001',
                    'is_komisi'  => false,
                ]
            );

            // Dosen Komisi
            $doskomUser = User::updateOrCreate(
                ['email' => 'doskom@sikap.test'],
                [
                    'name' => 'Dosen Komisi Contoh',
                    'password' => Hash::make('password'),
                    'role' => 'Dosen Komisi',
                    'email_verified_at' => $now,
                ]
            );
            Dosen::updateOrCreate(
                ['user_id' => $doskomUser->id],
                [
                    'jurusan_id' => $jurusan->id,
                    'nama_dosen' => $doskomUser->name,
                    'nip'        => '198202022006021002',
                    'is_komisi'  => true,
                ]
            );
        });
    }
}
