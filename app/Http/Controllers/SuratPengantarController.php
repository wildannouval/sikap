<?php

namespace App\Http\Controllers;

use App\Models\SuratPengantar;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Services\DocSigner;
use Carbon\Carbon;

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

public function cetak($id, \App\Services\DocSigner $signer)
{
    $m = \App\Models\SuratPengantar::with('mahasiswa.jurusan')->findOrFail($id);

    $values = [
        // ⇩ Sesuai TEMPLATE_SURAT_PENGANTAR.docx
        'nomor_surat_pengantar'            => $m->nomor_surat ?? '-',
        'tanggal_disetujui_surat_pengantar'=> \Carbon\Carbon::now()->translatedFormat('d F Y'),
        'penerima_surat'                   => $m->penerima_surat_pengantar ?? '-',
        'alamat_instansi'                  => $m->alamat_surat_pengantar ?? '-',
        'nama_mahasiswa'                   => optional($m->mahasiswa)->nama_mahasiswa ?? '-',
        'nim_mahasiswa'                    => optional($m->mahasiswa)->nim ?? '-',
        'jurusan_mahasiswa'                => optional($m->mahasiswa?->jurusan)->nama_jurusan ?? '-',
        'lokasi_instansi'                  => $m->lokasi_surat_pengantar ?? '-',
        'tembusan_surat_pengantar'         => $m->tembusan_surat_pengantar ?? '-',
    ];

    $docx = $signer->buildSignedDoc(
        model: $m,
        templatePath: storage_path('app/templates/TEMPLATE_SURAT_PENGANTAR.docx'),
        values: $values,
        outName: "surat_pengantar_{$m->uuid}",
        signerName: 'Wakil Dekan Bidang Akademik'
    );

    return response()->download($docx);
}

    // halaman verifikasi publik
    public function verifikasi($uuid)
    {
        // cari di 3 tabel (uuid unik cukup cek berurutan)
        $surat = \App\Models\SuratPengantar::where('uuid',$uuid)->first()
              ?? \App\Models\KerjaPraktek::where('uuid',$uuid)->first()
              ?? \App\Models\Seminar::where('uuid',$uuid)->first();

        $status = 'invalid'; $alasan = 'Data tidak ditemukan.';
        if ($surat) {
            $token = request('token','');
            if ($token && $surat->qr_token && \Illuminate\Support\Facades\Hash::check($token, $surat->qr_token)) {
                if (!$surat->qr_expires_at || now()->lte($surat->qr_expires_at)) {
                    $status = 'valid'; $alasan = 'Tanda tangan digital terverifikasi.';
                } else {
                    $status = 'kedaluwarsa'; $alasan = 'Token verifikasi sudah kedaluwarsa.';
                }
            } else { $alasan = 'Token verifikasi salah.'; }
        }

        return view('verifikasi.ttd', compact('status','alasan','surat'));
    }
}
