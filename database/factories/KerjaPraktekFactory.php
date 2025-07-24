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
        // Daftar judul KP yang lebih realistis
        $judul = [
            'Analisis Sistem Antrian pada Layanan Pelanggan PT. Telkom Purwokerto',
            'Perancangan Ulang Tata Letak Gudang Bahan Baku di PT. Sinar Maju',
            'Implementasi Metode 5S untuk Peningkatan Efisiensi Area Produksi',
            'Pengukuran Produktivitas Karyawan Menggunakan Metode Work Sampling',
            'Optimalisasi Rute Distribusi Produk Menggunakan Metode Saving Matrix',
        ];

        return [
            'judul_kp' => $judul[array_rand($judul)] . ' ' . fake()->company(),
            'lokasi_kp' => fake()->company(),
            'proposal_kp' => 'dokumen/placeholder.pdf',
            'surat_keterangan_kp' => 'dokumen/placeholder.pdf',
            'tanggal_pengajuan_kp' => fake()->dateTimeThisYear(),
        ];
    }
}
