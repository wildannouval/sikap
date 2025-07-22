<?php

namespace App\Http\Controllers;

use App\Models\KerjaPraktek;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class KerjaPraktekController extends Controller
{
    public function exportSpk($id)
    {
        $kp = KerjaPraktek::with('mahasiswa.jurusan')->findOrFail($id);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Contoh sederhana konten SPK
        $section->addText('SURAT PERINTAH KERJA PRAKTEK', ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $section->addText('Nomor: ...../UNIV/FAK/SPK-KP/'.date('Y'), null, ['alignment' => 'center']);
        $section->addTextBreak(2);
        $section->addText('Berdasarkan pengajuan yang telah disetujui, dengan ini menugaskan mahasiswa:');
        $section->addTextBreak(1);
        $section->addText("Nama: " . $kp->mahasiswa->nama_mahasiswa);
        $section->addText("NIM: " . $kp->mahasiswa->nim);
        $section->addText("Jurusan: " . $kp->mahasiswa->jurusan->nama_jurusan);
        $section->addTextBreak(1);
        $section->addText("Untuk melaksanakan Kerja Praktik dengan judul \"{$kp->judul_kp}\" di {$kp->lokasi_kp}.");
        $section->addTextBreak(2);
        $section->addText('Demikian surat perintah ini dibuat untuk dapat dilaksanakan dengan sebaik-baiknya.');
        $section->addTextBreak(4);
        $section->addText('Nama Pejabat Berwenang', ['bold' => true]);

        // Simpan dan paksa unduh file
        $filename = 'SPK_' . $kp->mahasiswa->nim . '.docx';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }
}
