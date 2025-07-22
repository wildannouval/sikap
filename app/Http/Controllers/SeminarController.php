<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class SeminarController extends Controller
{
    public function exportBeritaAcara($id)
    {
        $seminar = Seminar::with(['kerjaPraktek.mahasiswa'])->findOrFail($id);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Contoh sederhana konten Berita Acara
        $section->addText('BERITA ACARA SEMINAR KERJA PRAKTEK', ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $section->addTextBreak(2);
        $section->addText("Telah dilaksanakan Seminar Kerja Praktik atas nama:");
        $section->addText("Nama: " . $seminar->kerjaPraktek->mahasiswa->nama_mahasiswa);
        $section->addText("NIM: " . $seminar->kerjaPraktek->mahasiswa->nim);
        $section->addTextBreak(1);
        $section->addText("Judul: " . $seminar->judul_kp_final);
        $section->addText("Tanggal: " . \Carbon\Carbon::parse($seminar->tanggal_seminar)->format('d F Y'));
        $section->addText("Waktu: " . $seminar->jam_mulai . " - " . $seminar->jam_selesai);
        $section->addText("Tempat: " . $seminar->ruangan->nama_ruangan);
        $section->addTextBreak(4);
        $section->addText('Tanda Tangan Dosen Pembimbing,', null, ['alignment' => 'right']);

        // Simpan dan paksa unduh file
        $filename = 'Berita_Acara_' . $seminar->kerjaPraktek->mahasiswa->nim . '.docx';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }
}
