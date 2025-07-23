<?php

namespace App\Http\Controllers;

use App\Exports\KerjaPraktekExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function exportKp(Request $request)
    {
        // Ambil filter dari URL query
        $search = $request->query('q');
        $statusFilter = $request->query('statusFilter');
        $jurusanFilter = $request->query('jurusanFilter');

        // Generate nama file dinamis
        $fileName = 'Laporan_KP_' . now()->format('Y-m-d') . '.xlsx';

        // Panggil class export dengan membawa filter dan unduh file
        return Excel::download(new KerjaPraktekExport($search, $statusFilter, $jurusanFilter), $fileName);
    }
}
