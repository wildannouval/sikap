<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Konsultasi>
 */
class KonsultasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tanggal_konsultasi' => fake()->dateTimeThisYear(),
            'topik_konsultasi' => fake()->paragraph(2),
            'status_verifikasi' => 'Menunggu Verifikasi',
        ];
    }
}
