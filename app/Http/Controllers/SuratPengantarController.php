<?php

namespace App\Http\Controllers;

use App\Models\SuratPengantar;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class SuratPengantarController extends Controller
{
    /**
     * Fungsi untuk men-generate dan mengunduh surat pengantar dalam format Word.
     */
    public function exportWord($id)
    {
        // 1. Ambil data surat dari database beserta relasinya
        $surat = SuratPengantar::with('mahasiswa.jurusan')->findOrFail($id);

        // 2. Buat objek PHPWord baru
        $phpWord = new PhpWord();

        // 3. Tambahkan section (halaman) baru
        $section = $phpWord->addSection();

        // 4. Buat konten surat (ini adalah contoh sederhana)
        $section->addText('KOP SURAT UNIVERSITAS/FAKULTAS', ['bold' => true, 'size' => 14], ['alignment' => 'center']);
        $section->addTextBreak(1);
        $section->addText('SURAT PENGANTAR', ['bold' => true, 'underline' => 'single'], ['alignment' => 'center']);
        $section->addText('Nomor: ...../UNIV/FAK/KP/'.date('Y'), null, ['alignment' => 'center']);
        $section->addTextBreak(2);

        $section->addText('Kepada Yth.');
        $section->addText($surat->penerima_surat_pengantar);
        $section->addText('di');
        $section->addText($surat->alamat_surat_pengantar);
        $section->addTextBreak(1);

        $section->addText('Dengan hormat,');
        $section->addText(
            "    Dengan ini kami menerangkan bahwa mahasiswa di bawah ini:",
            null,
            ['indentation' => ['firstLine' => 720]] // indentasi paragraf
        );
        $section->addTextBreak(1);

        // Tabel data mahasiswa
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 80]);
        $table->addRow();
        $table->addCell(2000)->addText('Nama');
        $table->addCell(500)->addText(':');
        $table->addCell(7000)->addText($surat->mahasiswa->nama_mahasiswa, ['bold' => true]);
        $table->addRow();
        $table->addCell(2000)->addText('NIM');
        $table->addCell(500)->addText(':');
        $table->addCell(7000)->addText($surat->mahasiswa->nim, ['bold' => true]);
        $table->addRow();
        $table->addCell(2000)->addText('Jurusan');
        $table->addCell(500)->addText(':');
        $table->addCell(7000)->addText($surat->mahasiswa->jurusan->nama_jurusan, ['bold' => true]);
        $section->addTextBreak(1);

        $section->addText(
            "    adalah benar mahasiswa kami yang akan melaksanakan Kerja Praktik di instansi yang Bapak/Ibu pimpin. Mohon kiranya dapat diberikan bimbingan dan kesempatan.",
            null,
            ['indentation' => ['firstLine' => 720]]
        );
        $section->addTextBreak(2);
        $section->addText('Atas perhatian dan kerjasama Bapak/Ibu, kami ucapkan terima kasih.');
        $section->addTextBreak(3);
        $section->addText('Purwokerto, ' . date('d F Y'));
        $section->addText('a.n. Dekan,');
        $section->addText('Wakil Dekan Bidang Akademik,');
        $section->addTextBreak(4);
        $section->addText('Nama Pejabat', ['bold' => true, 'underline' => 'single']);
        $section->addText('NIP. ............................');

        // 5. Simpan dan paksa unduh file
        $filename = 'Surat_Pengantar_' . $surat->mahasiswa->nim . '.docx';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }
}
