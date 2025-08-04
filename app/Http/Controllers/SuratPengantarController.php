<?php

namespace App\Http\Controllers;

use App\Models\SuratPengantar;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class SuratPengantarController extends Controller
{
    /**
     * Fungsi untuk men-generate dan mengunduh surat pengantar dalam format Word.
     */
    public function exportWord($id)
    {
        $surat = SuratPengantar::with('mahasiswa.jurusan')->findOrFail($id);
        $templatePath = storage_path('app/templates/TEMPLATE_SURAT_PENGANTAR.docx');

        if (!file_exists($templatePath)) {
            abort(404, 'Template Surat Pengantar tidak ditemukan.');
        }
        $templateProcessor = new TemplateProcessor($templatePath);

        // Ganti placeholder dengan data dari database
        $templateProcessor->setValue('nomor_surat_pengantar', $surat->nomor_surat);
        $templateProcessor->setValue('tanggal_disetujui_surat_pengantar', \Carbon\Carbon::parse($surat->tanggal_disetujui_surat_pengantar)->translatedFormat('d F Y'));
        $templateProcessor->setValue('penerima_surat', $surat->penerima_surat_pengantar);
        $templateProcessor->setValue('alamat_instansi', $surat->alamat_surat_pengantar);
        $templateProcessor->setValue('nama_mahasiswa', $surat->mahasiswa->nama_mahasiswa);
        $templateProcessor->setValue('nim_mahasiswa', $surat->mahasiswa->nim);
        $templateProcessor->setValue('jurusan_mahasiswa', $surat->mahasiswa->jurusan->nama_jurusan);
        $templateProcessor->setValue('lokasi_instansi', $surat->lokasi_surat_pengantar);
        $templateProcessor->setValue('tembusan_surat_pengantar', $surat->tembusan_surat_pengantar ?? '');

        // Siapkan nama file untuk diunduh dan simpan sementara
        $filename = 'Surat_Pengantar_' . $surat->mahasiswa->nim . '.docx';
        $tempPath = storage_path('app/temp/' . $filename);

        if (!Storage::disk('local')->exists('temp')) {
            Storage::disk('local')->makeDirectory('temp');
        }
        $templateProcessor->saveAs($tempPath);

        // Unduh file lalu hapus dari server
        return response()->download($tempPath)->deleteFileAfterSend(true);
    }
}
