<?php

namespace Database\Factories;

use App\Models\Jurusan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MahasiswaFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake('id_ID');
        $angkatan = $faker->numberBetween(2019, 2024);
        $kodeJur = $faker->randomElement(['11','12','13']); // contoh kode jurusan
        $urut = str_pad((string)$faker->numberBetween(1,9999), 4, '0', STR_PAD_LEFT);
        $nim = substr((string)$angkatan, -2).'.'.$kodeJur.'.'.$urut;

        return [
        'nama_mahasiswa'  => $faker->name(),
        'nim'             => $nim,
        'tahun_angkatan'  => $angkatan, // ⬅️ ganti dari 'angkatan' ke 'tahun_angkatan'
        'jurusan_id'      => Jurusan::inRandomOrder()->value('id') ?? Jurusan::factory(),
        'no_hp'           => '08'.$faker->numberBetween(1111111111, 9999999999),
        'alamat'          => $faker->address(),
        'created_at'      => now(),
        'updated_at'      => now(),
        ];
    }
}
