<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Services\DocSigner;
use Carbon\Carbon;

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

public function cetakBap($id, \App\Services\DocSigner $signer)
{
    $m = \App\Models\Seminar::with([
        'kerjaPraktek.mahasiswa.jurusan',
        'kerjaPraktek.dosenPembimbing',
        'ruangan'
    ])->findOrFail($id);

    $waktuSeminar = trim(
        ($m->jam_mulai ? \Carbon\Carbon::parse($m->jam_mulai)->format('H:i') : '')
        . ($m->jam_selesai ? ' - ' . \Carbon\Carbon::parse($m->jam_selesai)->format('H:i') : '')
    );

    $values = [
        // ⇩ Sesuai TEMPLATE_BERITA_ACARA.docx
        'nama_mahasiswa'                  => optional($m->kerjaPraktek?->mahasiswa)->nama_mahasiswa ?? '-',
        'nim_mahasiswa'                   => optional($m->kerjaPraktek?->mahasiswa)->nim ?? '-',
        'jurusan_mahasiswa'               => optional($m->kerjaPraktek?->mahasiswa?->jurusan)->nama_jurusan ?? '-',
        // judul ambil dari KP final (kalau kamu simpan di kolom seminar gunakan itu; di template ada ${judul_kerja_praktik_mahasiswa})
        'judul_kerja_praktik_mahasiswa'   => $m->judul_kp_final ?? $m->kerjaPraktek->judul_kp ?? '-',
        'tanggal_seminar'                 => $m->tanggal_seminar ? \Carbon\Carbon::parse($m->tanggal_seminar)->translatedFormat('d F Y') : '-',
        'waktu_seminar'                   => $waktuSeminar ?: '-',
        'ruang_seminar'                   => optional($m->ruangan)->nama_ruangan ?? '-',
        'nama_dosen_pembimbing'           => optional($m->kerjaPraktek?->dosenPembimbing)->nama_dosen ?? '-',
        'nip_dosen_pembimbing'            => optional($m->kerjaPraktek?->dosenPembimbing)->nip ?? '-',
        'tanggal_berita_acara'            => \Carbon\Carbon::now()->translatedFormat('d F Y'),
    ];

    $docx = $signer->buildSignedDoc(
        model: $m,
        templatePath: storage_path('app/templates/TEMPLATE_BERITA_ACARA.docx'),
        values: $values,
        outName: "bap_{$m->uuid}",
        signerName: 'Ketua Jurusan Informatika'
    );

    return response()->download($docx);
}
}
