<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class SeminarController extends Controller
{
    public function exportBeritaAcara($id)
    {
        // 1. Ambil data seminar dari database beserta relasi yang dibutuhkan
        $seminar = Seminar::with([
            'kerjaPraktek.mahasiswa.jurusan',
            'kerjaPraktek.dosenPembimbing',
            'ruangan'
        ])->findOrFail($id);

        // Path ke file template Anda
        $templatePath = storage_path('app/templates/TEMPLATE_BERITA_ACARA.docx');

        if (!file_exists($templatePath)) {
            abort(404, 'Template Berita Acara tidak ditemukan.');
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // 2. Siapkan data untuk mengisi placeholder
        $mahasiswa = $seminar->kerjaPraktek->mahasiswa;
        $dosen = $seminar->kerjaPraktek->dosenPembimbing;
        $waktu_seminar = \Carbon\Carbon::parse($seminar->jam_mulai)->format('H:i') . ' - ' . \Carbon\Carbon::parse($seminar->jam_selesai)->format('H:i');

        // 3. Ganti semua placeholder dengan data dari database
        $templateProcessor->setValue('nama_mahasiswa', $mahasiswa->nama_mahasiswa);
        $templateProcessor->setValue('nim_mahasiswa', $mahasiswa->nim);
        $templateProcessor->setValue('jurusan_mahasiswa', $mahasiswa->jurusan->nama_jurusan);
        $templateProcessor->setValue('judul_kerja_praktik_mahasiswa', $seminar->judul_kp_final);
        $templateProcessor->setValue('nama_dosen_pembimbing', $dosen->nama_dosen ?? '-');
        $templateProcessor->setValue('nip_dosen_pembimbing', $dosen->nip ?? '-');
        $templateProcessor->setValue('tanggal_seminar', \Carbon\Carbon::parse($seminar->tanggal_seminar)->translatedFormat('l, d F Y'));
        $templateProcessor->setValue('waktu_seminar', $waktu_seminar);
        $templateProcessor->setValue('ruang_seminar', $seminar->ruangan->nama_ruangan);
        $templateProcessor->setValue('tanggal_berita_acara', now()->translatedFormat('d F Y'));


        // 4. Siapkan nama file untuk diunduh dan simpan sementara
        $tempDirectory = 'temp';
        if (!Storage::disk('local')->exists($tempDirectory)) {
            Storage::disk('local')->makeDirectory($tempDirectory);
        }
        $filename = 'Berita_Acara_Seminar_' . $mahasiswa->nim . '.docx';
        $tempPath = storage_path('app/' . $tempDirectory . DIRECTORY_SEPARATOR . $filename);
        $templateProcessor->saveAs($tempPath);

        // 5. Unduh file lalu hapus dari server
        return response()->download($tempPath)->deleteFileAfterSend(true);
    }
}
