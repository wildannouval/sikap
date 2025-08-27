<?php

namespace App\Http\Controllers;

use App\Models\KerjaPraktek;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use App\Services\DocSigner;
use Carbon\Carbon;


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

    public function cetakSpk($id, \App\Services\DocSigner $signer)
    {
    $m = \App\Models\KerjaPraktek::with(['mahasiswa.jurusan','dosenPembimbing'])->findOrFail($id);

    // Tanggal2 diambil dari kolom yang ada; fallback ke now() bila null
    $tanggalPenunjukan = $m->tanggal_disetujui_kp ? \Carbon\Carbon::parse($m->tanggal_disetujui_kp) : \Carbon\Carbon::now();
    $tanggalTerbit     = $m->tanggal_disetujui_spk ? \Carbon\Carbon::parse($m->tanggal_disetujui_spk) : \Carbon\Carbon::now();

    $values = [
        // ⇩ Sesuai TEMPLATE_SPK.docx
        'nomor_spk'                      => $m->nomor_spk ?? '-',
        'tanggal_penunjukan'             => $tanggalPenunjukan->translatedFormat('d F Y'),
        'tanggal_terbit_spk'             => $tanggalTerbit->translatedFormat('d F Y'),
        'nama_mahasiswa'                 => optional($m->mahasiswa)->nama_mahasiswa ?? '-',
        'nim_mahasiswa'                  => optional($m->mahasiswa)->nim ?? '-',
        'jurusan_mahasiswa'              => optional($m->mahasiswa?->jurusan)->nama_jurusan ?? '-',
        'judul_kerja_praktik_mahasiswa'  => $m->judul_kp ?? '-',            // <- pakai judul_kp
        'nama_dosen_pembimbing'          => optional($m->dosenPembimbing)->nama_dosen ?? '-', // <- nama_dosen
        'nip_dosen_pembimbing'           => optional($m->dosenPembimbing)->nip ?? '-',
    ];

    $docx = $signer->buildSignedDoc(
        model: $m,
        templatePath: storage_path('app/templates/TEMPLATE_SPK.docx'),
        values: $values,
        outName: "spk_{$m->uuid}",
        signerName: 'Ketua Jurusan Informatika'
    );

    return response()->download($docx);
    }
}
