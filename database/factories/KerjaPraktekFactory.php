<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KerjaPraktekFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake('id_ID');
        $judul = 'Pengembangan '.$faker->words(3, true).' pada '.$faker->company();

        return [
            'judul_kp' => $judul,
            'lokasi_kp' => $faker->city(),
            'status_pengajuan_kp' => $faker->randomElement(['Diajukan','Proses di Komisi','Disetujui','SPK Terbit']),
            'status_kp' => $faker->randomElement(['Berlangsung','Selesai']),
            'tanggal_pengajuan_kp' => now()->subDays(rand(20,60)),
            'tanggal_disetujui_kp' => now()->subDays(rand(10,30)),
            'tanggal_disetujui_spk' => now()->subDays(rand(1,10)),
            'nomor_spk' => 'SPK/'.now()->format('Y').'/'.str_pad((string)rand(1,999),3,'0',STR_PAD_LEFT),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
