<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Seminar>
 */
class SeminarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'judul_kp_final' => fake()->sentence(7),
            'berkas_laporan_final' => 'dokumen/placeholder.pdf',
            'tanggal_seminar' => fake()->dateTimeThisYear(),
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:00',
        ];
    }
}
