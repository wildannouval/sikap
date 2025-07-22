<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SuratPengantar>
 */
class SuratPengantarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lokasi_surat_pengantar' => fake()->company(),
            'penerima_surat_pengantar' => 'Yth. Manajer HRD',
            'alamat_surat_pengantar' => fake()->address(),
            'status_surat_pengantar' => 'Diajukan',
            'tanggal_pengajuan_surat_pengantar' => fake()->dateTimeThisYear(),
        ];
    }
}
