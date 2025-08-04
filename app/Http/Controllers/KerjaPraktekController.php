<?php

namespace App\Http\Controllers;

use App\Models\KerjaPraktek;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;

class KerjaPraktekController extends Controller
{
    public function exportSpk($id)
    {
        $kp = KerjaPraktek::with('mahasiswa.jurusan', 'dosenPembimbing')->findOrFail($id);

        $templatePath = storage_path('app' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'TEMPLATE_SPK.docx');

        if (!file_exists($templatePath)) {
            abort(404, 'Template SPK tidak ditemukan.');
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // Ganti placeholder dengan data dari database
        $templateProcessor->setValue('nomor_spk', $kp->nomor_spk);
        $templateProcessor->setValue('tanggal_penunjukan', \Carbon\Carbon::parse($kp->tanggal_disetujui_kp)->translatedFormat('d F Y'));
        $templateProcessor->setValue('nama_mahasiswa', $kp->mahasiswa->nama_mahasiswa);
        $templateProcessor->setValue('nim_mahasiswa', $kp->mahasiswa->nim);
        $templateProcessor->setValue('jurusan_mahasiswa', $kp->mahasiswa->jurusan->nama_jurusan);
        $templateProcessor->setValue('judul_kerja_praktik_mahasiswa', $kp->judul_kp);
        $templateProcessor->setValue('nama_dosen_pembimbing', $kp->dosenPembimbing->nama_dosen ?? '-');
        $templateProcessor->setValue('nip_dosen_pembimbing', $kp->dosenPembimbing->nip ?? '-');
        $templateProcessor->setValue('tanggal_terbit_spk', \Carbon\Carbon::parse($kp->tanggal_disetujui_spk)->translatedFormat('d F Y'));

        // 1. Definisikan path folder sementara
        $tempDirectory = 'temp';

        // 2. Buat folder 'temp' jika belum ada
        if (!Storage::disk('local')->exists($tempDirectory)) {
            Storage::disk('local')->makeDirectory($tempDirectory);
        }

        // 3. Siapkan nama file dan path lengkapnya
        $filename = 'SPK_' . $kp->mahasiswa->nim . '.docx';
        $tempPath = storage_path('app' . DIRECTORY_SEPARATOR . $tempDirectory . DIRECTORY_SEPARATOR . $filename);

        // Simpan file yang sudah diisi ke lokasi sementara
        $templateProcessor->saveAs($tempPath);

        // Unduh file lalu hapus dari server
        return response()->download($tempPath)->deleteFileAfterSend(true);
    }
}
