<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DosenFactory extends Factory
{
    public function definition(): array
    {
        $faker = fake('id_ID');
        $nip = $faker->numerify(str_repeat('#',18));

        return [
            'nama_dosen' => 'Dr. '.$faker->lastName().' '.$faker->firstName(),
            'nip' => $nip,
            'email' => $faker->unique()->safeEmail(),
            'no_hp' => '08'.$faker->numberBetween(1111111111, 9999999999),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
