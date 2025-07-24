<?php

namespace App\Exports;

use App\Models\KerjaPraktek;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class KerjaPraktekExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;
    protected $startDate;
    protected $endDate;

    // Properti untuk menerima filter dari controller
    protected $search;
    protected $statusFilter;
    protected $jurusanFilter;

    public function __construct($search, $statusFilter, $jurusanFilter, $startDate, $endDate)
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->jurusanFilter = $jurusanFilter;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Mendefinisikan header untuk file Excel.
     */
    public function headings(): array
    {
        return [
            'Nama Mahasiswa',
            'NIM',
            'Jurusan',
            'Judul KP',
            'Lokasi KP',
            'Dosen Pembimbing',
            'Status KP',
            'Nilai Akhir',
        ];
    }

    /**
     * Memetakan data dari setiap baris ke kolom yang sesuai.
     */
    public function map($kp): array
    {
        return [
            $kp->mahasiswa->nama_mahasiswa,
            $kp->mahasiswa->nim,
            $kp->mahasiswa->jurusan->nama_jurusan,
            $kp->judul_kp,
            $kp->lokasi_kp,
            $kp->dosenPembimbing->nama_dosen ?? '-',
            $kp->status_kp ?? 'Belum Dimulai',
            $kp->seminar->nilai_akhir ?? '-',
        ];
    }

    /**
     * Query untuk mengambil data dari database, lengkap dengan filter.
     */
    public function query()
    {
        return KerjaPraktek::query()->with(['mahasiswa.jurusan', 'dosenPembimbing', 'seminar'])
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('judul_kp', 'like', '%' . $this->search . '%')
                        ->orWhereHas('mahasiswa', fn($q) => $q->where('nama_mahasiswa', 'like', '%' . $this->search . '%')
                            ->orWhere('nim', 'like', '%' . $this->search . '%'))
                        ->orWhereHas('dosenPembimbing', fn($q) => $q->where('nama_dosen', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status_kp', $this->statusFilter);
            })
            ->when($this->jurusanFilter, function ($query) {
                $query->whereHas('mahasiswa', fn($q) => $q->where('jurusan_id', $this->jurusanFilter));
            })
            // Tambahkan query filter tanggal di sini juga
            ->when($this->startDate, function ($query) {
                $query->whereDate('tanggal_pengajuan_kp', '>=', $this->startDate);
            })
            ->when($this->endDate, function ($query) {
                $query->whereDate('tanggal_pengajuan_kp', '<=', $this->endDate);
            })
            ->when($this->startDate && $this->endDate, function ($query) {
                $query->whereBetween('tanggal_pengajuan_kp', [$this->startDate, $this->endDate]);
            });
    }
}
