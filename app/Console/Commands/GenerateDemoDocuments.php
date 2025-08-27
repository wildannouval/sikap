<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

use App\Models\SuratPengantar;
use App\Models\KerjaPraktek;
use App\Models\Seminar;

class GenerateDemoDocuments extends Command
{
    protected $signature = 'demo:generate-docs {--force : Timpa file jika sudah ada}';
    protected $description = 'Generate dokumen demo (Surat Pengantar, SPK, BAP) dari template seperti alur Bapendik, plus QR TTD KAJUR.';

    public function handle(): int
    {
        $tplDir = resource_path('templates');
        $tplSurat = $tplDir.'/TEMPLATE_SURAT_PENGANTAR.docx';
        $tplSpk   = $tplDir.'/TEMPLATE_SPK.docx';
        $tplBap   = $tplDir.'/TEMPLATE_BERITA_ACARA.docx';

        foreach ([$tplSurat, $tplSpk, $tplBap] as $p) {
            if (!file_exists($p)) {
                $this->error("Template tidak ditemukan: $p");
                $this->line('Pastikan tiga file template ada di resources/templates/');
                return self::FAILURE;
            }
        }

        // Pastikan folder tujuan ada
        Storage::disk('public')->makeDirectory('demo/surat_pengantar');
        Storage::disk('public')->makeDirectory('demo/spk');
        Storage::disk('public')->makeDirectory('demo/bap');
        Storage::disk('public')->makeDirectory('demo/qr');

        // URL verifikasi QR (kalau punya route verifikasi berbasis uuid)
        $makeVerifyUrl = function (string $uuid = null) {
            if ($uuid) {
                // ganti sesuai rute verifikasimu jika ada:
                return url("/ttd/verify/{$uuid}");
            }
            return config('app.url');
        };

        // ===== 1) SURAT PENGANTAR (yang Disetujui / Siap Diambil dll) =====
        $this->info('Membuat dokumen Surat Pengantar...');
        SuratPengantar::query()
            ->whereIn('status_surat_pengantar', ['Disetujui', 'Siap Diambil', 'Diajukan', 'Ditolak'])
            ->with(['mahasiswa.jurusan'])
            ->orderBy('id')
            ->each(function (SuratPengantar $sp) use ($tplSurat, $makeVerifyUrl) {
                $destRel = "demo/surat_pengantar/surat_pengantar_{$sp->id}.docx";
                $destAbs = Storage::disk('public')->path($destRel);

                if (!$this->option('force') && file_exists($destAbs)) {
                    $this->line("  • Lewat (sudah ada): $destRel");
                    return;
                }

                $tpl = new TemplateProcessor($tplSurat);

                $mhs = $sp->mahasiswa;
                $jur = $mhs?->jurusan?->nama_jurusan ?? '-';

                $tpl->setValue('nomor_surat', $sp->nomor_surat ?? '—');
                $tpl->setValue('nama_mahasiswa', $mhs?->nama_mahasiswa ?? '—');
                $tpl->setValue('nim', $mhs?->nim ?? '—');
                $tpl->setValue('jurusan', $jur);
                $tpl->setValue('tujuan', $sp->penerima_surat_pengantar ?? '—');
                $tpl->setValue('alamat', $sp->alamat_surat_pengantar ?? '—');
                $tpl->setValue('lokasi', $sp->lokasi_surat_pengantar ?? '—');

                $tgl = $sp->tanggal_pengajuan_surat_pengantar ?? $sp->created_at ?? now();
                $tpl->setValue('tanggal', Carbon::parse($tgl)->translatedFormat('d F Y'));

                // QR utk TTD KAJUR
                $qrPngPath = $this->makeQrFor('sp', $sp->uuid, $makeVerifyUrl($sp->uuid));
                $tpl->setImageValue('qr_kajur', [
                    'path' => $qrPngPath,
                    'width' => 140,  // atur ukuran QR
                    'height' => 140,
                    'ratio' => true,
                ]);

                // Simpan
                @unlink($destAbs);
                $tpl->saveAs($destAbs);
                $this->line("  ✓ $destRel");
            });

        // ===== 2) SPK (yang SPK Terbit / Berlangsung / Disetujui) =====
        $this->info('Membuat dokumen SPK...');
        KerjaPraktek::query()
            ->whereIn('status_pengajuan_kp', ['SPK Terbit', 'Disetujui', 'Proses di Komisi', 'Diajukan'])
            ->with(['mahasiswa', 'dosenPembimbing'])
            ->orderBy('id')
            ->each(function (KerjaPraktek $kp) use ($tplSpk, $makeVerifyUrl) {
                $destRel = "demo/spk/spk_{$kp->id}.docx";
                $destAbs = Storage::disk('public')->path($destRel);

                if (!$this->option('force') && file_exists($destAbs)) {
                    $this->line("  • Lewat (sudah ada): $destRel");
                    return;
                }

                $tpl = new TemplateProcessor($tplSpk);

                $mhs = $kp->mahasiswa;
                $dospem = $kp->dosenPembimbing;

                $tpl->setValue('nomor_spk', $kp->nomor_spk ?? '—');
                $tpl->setValue('nama_mahasiswa', $mhs?->nama_mahasiswa ?? '—');
                $tpl->setValue('nim', $mhs?->nim ?? '—');
                $tpl->setValue('judul_kp', $kp->judul_kp ?? '—');
                $tpl->setValue('dosen_pembimbing', $dospem?->nama_dosen ?? '—');

                $tgl = $kp->tanggal_disetujui_spk ?? $kp->tanggal_disetujui_kp ?? $kp->created_at ?? now();
                $tpl->setValue('tanggal', Carbon::parse($tgl)->translatedFormat('d F Y'));

                $qrPngPath = $this->makeQrFor('spk', $kp->uuid, $makeVerifyUrl($kp->uuid));
                $tpl->setImageValue('qr_kajur', [
                    'path' => $qrPngPath,
                    'width' => 140,
                    'height' => 140,
                    'ratio' => true,
                ]);

                @unlink($destAbs);
                $tpl->saveAs($destAbs);
                $this->line("  ✓ $destRel");
            });

        // ===== 3) BAP (Seminar Dijadwalkan/Dinilai) =====
        $this->info('Membuat dokumen BAP...');
        Seminar::query()
            ->whereIn('status_seminar', ['Diajukan','Dijadwalkan','Dinilai'])
            ->with(['kerjaPraktek.mahasiswa', 'ruangan'])
            ->orderBy('id')
            ->each(function (Seminar $sem) use ($tplBap, $makeVerifyUrl) {
                $destRel = "demo/bap/bap_{$sem->id}.docx";
                $destAbs = Storage::disk('public')->path($destRel);

                if (!$this->option('force') && file_exists($destAbs)) {
                    $this->line("  • Lewat (sudah ada): $destRel");
                    return;
                }

                $tpl = new TemplateProcessor($tplBap);

                $kp = $sem->kerjaPraktek;
                $mhs = $kp?->mahasiswa;

                $tpl->setValue('judul_kp_final', $sem->judul_kp_final ?? $kp?->judul_kp ?? '—');
                $tpl->setValue('nama_mahasiswa', $mhs?->nama_mahasiswa ?? '—');
                $tpl->setValue('nim', $mhs?->nim ?? '—');
                $tpl->setValue('tanggal_seminar', $sem->tanggal_seminar ? Carbon::parse($sem->tanggal_seminar)->translatedFormat('d F Y') : '—');
                $tpl->setValue('jam_mulai', $sem->jam_mulai ?? '—');
                $tpl->setValue('jam_selesai', $sem->jam_selesai ?? '—');
                $tpl->setValue('ruangan', $sem->ruangan?->nama_ruangan ?? '—');
                $tpl->setValue('nilai_akhir', $sem->nilai_akhir ?? '—');

                $qrPngPath = $this->makeQrFor('bap', $sem->uuid, $makeVerifyUrl($sem->uuid));
                $tpl->setImageValue('qr_kajur', [
                    'path' => $qrPngPath,
                    'width' => 140,
                    'height' => 140,
                    'ratio' => true,
                ]);

                @unlink($destAbs);
                $tpl->saveAs($destAbs);
                $this->line("  ✓ $destRel");
            });

        $this->info('Selesai. Dokumen demo tersedia di public/storage/demo/*');
        $this->line('Jika tombol download view kamu sudah mengarah ke path storage/public, dokumen akan terunduh.');
        return self::SUCCESS;
    }

    /**
     * Generate QR PNG ke storage sementara, lalu return path file absolute untuk TemplateProcessor.
     */
    protected function makeQrFor(string $type, ?string $uuid, string $url): string
    {
        $rel = 'demo/qr/'.($uuid ?: Str::uuid())."_{$type}.png";
        $png = QrCode::format('png')->size(300)->margin(1)->generate($url);
        Storage::disk('public')->put($rel, $png);
        return Storage::disk('public')->path($rel);
    }
}
