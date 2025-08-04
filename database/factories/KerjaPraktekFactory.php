<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KerjaPraktek>
 */
class KerjaPraktekFactory extends Factory
{
    public function definition(): array
    {
        $judul = [
            'Analisis Sistem Antrian pada Layanan Pelanggan',
            'Perancangan Ulang Tata Letak Gudang Bahan Baku',
            'Implementasi Metode 5S untuk Peningkatan Efisiensi',
            'Pengukuran Produktivitas Karyawan Menggunakan Work Sampling',
            'Optimalisasi Rute Distribusi Produk Menggunakan Saving Matrix',
        ];

        return [
            'judul_kp' => $judul[array_rand($judul)] . ' di ' . fake()->company(),
            'lokasi_kp' => fake()->company(),
            'proposal_kp' => 'dokumen/placeholder.pdf',
            'surat_keterangan_kp' => 'dokumen/placeholder.pdf',
            'tanggal_pengajuan_kp' => fake()->dateTimeThisYear(),
        ];
    }
}
