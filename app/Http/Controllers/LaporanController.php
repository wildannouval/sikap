<?php

namespace App\Http\Controllers;

use App\Exports\KerjaPraktekExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function exportKp(Request $request)
    {
        // Ambil SEMUA filter dari URL query
        $search = $request->query('q');
        $statusFilter = $request->query('statusFilter');
        $jurusanFilter = $request->query('jurusanFilter');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $fileName = 'Laporan_KP_' . now()->format('Y-m-d') . '.xlsx';

        // Panggil class export dengan membawa SEMUA filter
        return Excel::download(new KerjaPraktekExport($search, $statusFilter, $jurusanFilter, $startDate, $endDate), $fileName);
    }
}
