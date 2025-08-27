<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SeminarFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake('id_ID');
        $judul = 'Implementasi '.$faker->words(3, true).' pada '.$faker->company();
        $tanggal = now()->addDays(rand(-7,14))->toDateString();

        return [
            'judul_kp_final' => $judul,
            'tanggal_seminar' => $tanggal,
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '10:30:00',
            'status_seminar' => $faker->randomElement(['Diajukan','Dijadwalkan','Selesai','Dinilai']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
