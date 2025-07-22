<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mahasiswa>
 */
class MahasiswaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // user_id, jurusan_id, dan nama_mahasiswa akan diisi dari Seeder
            'nim' => 'G1A0' . fake()->unique()->numerify('#####'),
            'tahun_angkatan' => fake()->numberBetween(2019, 2022),
        ];
    }
}
