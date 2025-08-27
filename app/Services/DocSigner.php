<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

// QR code via GD (tanpa imagick)
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class DocSigner
{
    /**
     * @param  object $model        Instance: SuratPengantar/KerjaPraktek/Seminar (punya kolom uuid, qr_token, dst.)
     * @param  string $templatePath storage_path('app/templates/TEMPLATE_XXX.docx')
     * @param  array  $values       ['placeholder' => 'value', ...] untuk setValue()
     * @param  string $outName      nama file output tanpa ekstensi
     * @param  string $signerName   nama pejabat penandatangan (Ketua Jurusan/WD)
     * @param  int    $validMonths  masa berlaku token (default 24 bulan)
     * @return string               absolute path ke DOCX tersimpan
     */
    public function buildSignedDoc($model, string $templatePath, array $values, string $outName, string $signerName, int $validMonths = 24): string
    {
        // 1) token verifikasi (hash disimpan di DB)
        $raw = Str::random(32);
        $model->qr_token      = Hash::make($raw);
        $model->qr_expires_at = now()->addMonths($validMonths);
        $model->ttd_signed_at = now();
        $model->ttd_signed_by = $signerName;
        $model->save();

        // 2) URL verifikasi publik
        $verifyUrl = route('verifikasi.ttd', ['uuid' => $model->uuid]) . '?token=' . $raw;

        // 3) Generate QR PNG via GD (tanpa imagick)
        $qrPng  = $this->makeQrPngGD($verifyUrl, 120); // 120px (nanti dipasang di docx 120x120)
        $qrPath = "qr/{$outName}.png";
        Storage::disk('public')->put($qrPath, $qrPng);

        // 4) Isi template
        $tp = new TemplateProcessor($templatePath);
        foreach ($values as $k => $v) {
            $tp->setValue($k, $v);
        }

        // placeholder gambar QR pada template: ${qr_code_ttd}
        $tp->setImageValue('qr_code_ttd', [
            'path'   => storage_path('app/public/'.$qrPath),
            'width'  => 70,
            'height' => 70,
            'ratio'  => false,
        ]);

        // 5) simpan DOCX
        $outPath = storage_path("app/public/surat/{$outName}.docx");
        @mkdir(dirname($outPath), 0775, true);
        $tp->saveAs($outPath);

        return $outPath;
    }

    /**
     * Generate QR PNG (binary string) menggunakan chillerlan/php-qrcode (GD).
     *
     * @param string $text  Konten QR (URL verifikasi)
     * @param int    $size  ukuran sisi PNG (px). scale = size/25 kira2
     * @return string       PNG binary
     */
    private function makeQrPngGD(string $text, int $size = 120): string
    {
        // Perkirakan scale (25 modules default). Tambah quietzone internal library.
        $scale = max(2, (int)round($size / 25));

        $options = new QROptions([
            'outputType'   => QRCode::OUTPUT_IMAGE_PNG, // PNG
            'imageBase64'  => false,                    // kembalikan biner, bukan data-uri
            'scale'        => $scale,                   // "besar modul"
            'eccLevel'     => QRCode::ECC_H,            // error correction tinggi (H)
            'addQuietzone' => true,
            'quietzoneSize'=> 2,                        // margin modul
        ]);

        return (new QRCode($options))->render($text);
    }
}
