<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PenggunaController extends Controller
{
    public function downloadTemplate()
    {
        // PERBAIKAN: Gunakan helper storage_path() untuk mendapatkan path absolut
        $path = storage_path('app/templates/template_pengguna.xlsx');

        // Pengecekan file tetap sama
        if (!file_exists($path)) {
            abort(404, 'File template tidak ditemukan.');
        }

        // Panggil fungsi download dengan path absolut
        return response()->download($path);
    }
}
