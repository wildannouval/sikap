<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SuratPengantarFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake('id_ID');
        $bulan = (int)now()->format('n');
        $romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][$bulan];
        $urut = str_pad((string)$faker->numberBetween(1,999),3,'0',STR_PAD_LEFT);

        return [
            'nomor_surat' => "FT/IF/KP/{$romawi}/".now()->format('Y')."/{$urut}",
            'penerima_surat_pengantar' => $faker->company(),
            'alamat_surat_pengantar' => $faker->address(),
            'lokasi_surat_pengantar' => $faker->city(),
            'tembusan_surat_pengantar' => 'Arsip, Bapendik',
            'status_surat_pengantar' => $faker->randomElement(['Diajukan','Siap Diambil','Disetujui']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
