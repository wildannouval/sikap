<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KerjaPraktek>
 */
class KerjaPraktekFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'judul_kp' => fake()->sentence(6),
            'lokasi_kp' => fake()->company(),
            'proposal_kp' => 'dokumen/proposal_placeholder.pdf',
            'surat_keterangan_kp' => 'dokumen/surat_keterangan_placeholder.pdf',
            'tanggal_pengajuan_kp' => fake()->dateTimeThisYear(),
        ];
    }
}
