<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dosen>
 */
class DosenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // user_id, jurusan_id, dan nama_dosen akan diisi dari Seeder
            'nip' => fake()->unique()->numerify('198#########'.fake()->numberBetween(1, 9)),
            'is_komisi' => false, // Defaultnya bukan anggota komisi
        ];
    }
}
