<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class DocSigner
{
    /**
     * Bangun dokumen bertanda-tangan (QR) dari template DOCX.
     *
     * @param  object $model        Model: SuratPengantar|KerjaPraktek|Seminar (punya kolom: uuid, qr_token, qr_expires_at, ttd_signed_at, ttd_signed_by)
     * @param  string $templatePath Boleh: path lengkap ATAU hanya nama file template (mis. 'TEMPLATE_SPK.docx')
     * @param  array  $values       Key/value untuk TemplateProcessor::setValue()
     * @param  string $outName      Nama file output (tanpa ekstensi)
     * @param  string $signerName   Nama pejabat penandatangan (mis. Ketua Jurusan)
     * @param  int    $validMonths  Masa berlaku token (bulan)
     * @param  int    $qrSizePx     Ukuran QR PNG (px), default 120
     * @return string               Absolute path file DOCX hasil generate
     */
    public function buildSignedDoc(
        $model,
        string $templatePath,
        array $values,
        string $outName,
        string $signerName,
        int $validMonths = 24,
        int $qrSizePx = 120
    ): string {
        // 1) Token verifikasi (hash disimpan di DB)
        $rawToken              = Str::random(32);
        $model->qr_token       = Hash::make($rawToken);
        $model->qr_expires_at  = now()->addMonths($validMonths);
        $model->ttd_signed_at  = now();
        $model->ttd_signed_by  = $signerName;
        $model->save();

        // 2) URL verifikasi publik
        $verifyUrl = route('verifikasi.ttd', ['uuid' => $model->uuid]).'?token='.$rawToken;

        // 3) Generate QR PNG via GD (tanpa imagick)
        $qrBinary = $this->makeQrPngGD($verifyUrl, $qrSizePx);
        $qrRel    = "qr/{$outName}.png";
        Storage::disk('public')->put($qrRel, $qrBinary);

        // 4) Resolusi path template yang benar
        $resolvedTemplate = $this->resolveTemplatePath($templatePath);

        // 5) Isi template DOCX
        $tp = new TemplateProcessor($resolvedTemplate);

        foreach ($values as $k => $v) {
            $tp->setValue($k, $v);
        }

        // Placeholder gambar QR pada template: ${qr_code_ttd}
        $tp->setImageValue('qr_code_ttd', [
            'path'   => storage_path('app/public/'.$qrRel),
            'width'  => 70,   // atur agar pas dengan kotak tanda tangan di template
            'height' => 70,
            'ratio'  => false,
        ]);

        // 6) Simpan DOCX ke storage publik
        $outPath = storage_path("app/public/surat/{$outName}.docx");
        if (! is_dir(dirname($outPath))) {
            @mkdir(dirname($outPath), 0775, true);
        }
        $tp->saveAs($outPath);

        return $outPath;
    }

    /**
     * Resolve path template:
     *  - Jika $templatePath sudah menunjuk file yang ada -> pakai itu
     *  - Jika hanya nama file -> cek resources/templates/<file>
     *  - Jika tidak ada -> cek storage/app/templates/<file>
     *  - Kalau tetap tak ketemu -> throw exception yang jelas
     */
    private function resolveTemplatePath(string $templatePath): string
    {
        // 1) Jika path yang dikirim sudah ada
        if (is_file($templatePath)) {
            return $templatePath;
        }

        // Ambil hanya nama file (kalau user ngirim path, kita tetap pakai basename)
        $filename = basename($templatePath);

        // 2) Cek di resources/templates (direkomendasikan disimpan di repo)
        $resPath = resource_path('templates/'.$filename);
        if (is_file($resPath)) {
            return $resPath;
        }

        // 3) Cek di storage/app/templates (fallback)
        $stoPath = storage_path('app/templates/'.$filename);
        if (is_file($stoPath)) {
            return $stoPath;
        }

        throw new \RuntimeException("Template tidak ditemukan di salah satu lokasi: 
- Dikirim: {$templatePath}
- resources/templates/{$filename}
- storage/app/templates/{$filename}");
    }

    /**
     * Generate QR PNG (binary string) pakai chillerlan/php-qrcode (GD).
     *
     * @param string $text   Konten QR (URL verifikasi)
     * @param int    $sizePx Ukuran sisi PNG (px). scale ≈ size/25
     * @return string        PNG binary
     */
    private function makeQrPngGD(string $text, int $sizePx = 120): string
    {
        // Perkirakan scale (25 modules default). Tambah quiet zone dari lib.
        $scale = max(2, (int)round($sizePx / 25));

        $options = new QROptions([
            'outputType'    => QRCode::OUTPUT_IMAGE_PNG, // PNG
            'imageBase64'   => false,                    // kembalikan biner, bukan data-uri
            'scale'         => $scale,                   // besar modul
            'eccLevel'      => QRCode::ECC_H,            // koreksi error tinggi
            'addQuietzone'  => true,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($text);
    }
}
