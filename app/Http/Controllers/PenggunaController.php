<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PenggunaController extends Controller
{
    // public function downloadTemplate()
    // {
    //     // PERBAIKAN: Gunakan helper storage_path() untuk mendapatkan path absolut
    //     $path = storage_path('app/templates/template_pengguna.xlsx');

    //     // Pengecekan file tetap sama
    //     if (!file_exists($path)) {
    //         abort(404, 'File template tidak ditemukan.');
    //     }

    //     // Panggil fungsi download dengan path absolut
    //     return response()->download($path);
    // }

    public function template()
{
    $filename = 'template_pengguna.csv';
    $headers = [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
    ];

    // contoh kolom yang biasanya dipakai untuk import
    $csv = implode(",", [
        'name',
        'email',
        'role',           // contoh: Mahasiswa|Dosen Pembimbing|Dosen Komisi|Bapendik
        'password',       // opsional; kalau kosong bisa generate random di import
        'jurusan_kode',   // opsional utk Mahasiswa/Dosen, mis. IF/SI/TE
        'nim',            // opsional utk Mahasiswa
        'nip',            // opsional utk Dosen
    ]) . "\n";

    return response($csv, 200, $headers);
}

}
