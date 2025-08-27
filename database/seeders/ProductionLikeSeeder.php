<?php

namespace Database\Seeders;

use App\Models\Dosen;
use App\Models\Jurusan;
use App\Models\KerjaPraktek;
use App\Models\Konsultasi;
use App\Models\Mahasiswa;
use App\Models\Ruangan;
use App\Models\Seminar;
use App\Models\SuratPengantar;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProductionLikeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ---- MASTER DATA ----
            $this->command?->info('Menyiapkan data master...');

            $now = now();
            Jurusan::upsert([
                ['kode_jurusan' => 'IF', 'nama_jurusan' => 'Teknik Informatika', 'created_at' => $now, 'updated_at' => $now],
                ['kode_jurusan' => 'SI', 'nama_jurusan' => 'Sistem Informasi',   'created_at' => $now, 'updated_at' => $now],
                ['kode_jurusan' => 'TE', 'nama_jurusan' => 'Teknik Elektro',     'created_at' => $now, 'updated_at' => $now],
            ], ['kode_jurusan'], ['nama_jurusan', 'updated_at']);

            $jurusanTI = Jurusan::where('kode_jurusan', 'IF')->first();
            $jurusanSI = Jurusan::where('kode_jurusan', 'SI')->first();
            $jurusanTE = Jurusan::where('kode_jurusan', 'TE')->first();

            Ruangan::upsert([
                ['nama_ruangan' => 'Ruang Seminar F1',   'lokasi_gedung' => 'Gedung F', 'created_at' => $now, 'updated_at' => $now],
                ['nama_ruangan' => 'Ruang Rapat Dekanat','lokasi_gedung' => 'Gedung A', 'created_at' => $now, 'updated_at' => $now],
                ['nama_ruangan' => 'Aula Gedung C',      'lokasi_gedung' => 'Gedung C', 'created_at' => $now, 'updated_at' => $now],
            ], ['nama_ruangan'], ['lokasi_gedung', 'updated_at']);

            $ruangans = Ruangan::pluck('id');

            // ---- AKUN STATIS / ROLE ----
            $this->command?->info('Membuat akun statis...');

            User::updateOrCreate(
                ['email' => 'bapendik@sikap.test'],
                [
                    'name'              => 'Admin Bapendik',
                    'password'          => Hash::make('password'),
                    'role'              => 'Bapendik',
                    'email_verified_at' => $now,
                ]
            );

            $dosenKomisiUser = User::updateOrCreate(
                ['email' => 'doskom@sikap.test'],
                [
                    'name'              => 'Prof. Dr. Dosen Komisi',
                    'password'          => Hash::make('password'),
                    'role'              => 'Dosen Komisi',
                    'email_verified_at' => $now,
                ]
            );

            Dosen::updateOrCreate(
                // gunakan user_id sebagai kunci idempotent
                ['user_id' => $dosenKomisiUser->id],
                [
                    'jurusan_id' => $jurusanTI->id,
                    'nama_dosen' => $dosenKomisiUser->name,
                    // ⬇️ nip harus BERBEDA dari Dosen Pembimbing
                    'nip'        => '198202022006021002',
                    'is_komisi'  => true,
                ]
            );

            // ---- DOSEN PEMBIMBING ----
            $this->command?->info('Membuat data dosen...');
            $dosenPembimbings = collect();
            for ($i = 1; $i <= 10; $i++) {
                $u = User::updateOrCreate(
                    ['email' => "dosen{$i}@sikap.test"],
                    [
                        'name'              => "Dosen {$i}",
                        'password'          => Hash::make('password'),
                        'role'              => 'Dosen Pembimbing',
                        'email_verified_at' => $now,
                    ]
                );
                $d = Dosen::updateOrCreate(
                    ['user_id' => $u->id],
                    [
                        'jurusan_id' => $jurusanTI->id,
                        'nama_dosen' => $u->name,
                        'nip'        => sprintf('1979%014d', $i),
                        'is_komisi'  => false,
                    ]
                );
                $dosenPembimbings->push($d);
            }

            // ---- MAHASISWA ----
            $this->command?->info('Membuat data mahasiswa...');
            $mahasiswas = collect();

            // daftar kolom yang benar-benar ada di tabel
            $mhCols = Schema::getColumnListing('mahasiswas'); // contoh: ['id','user_id','jurusan_id','nama_mahasiswa','nim','tahun_angkatan', ...]
            $col = fn(string $k) => in_array($k, $mhCols, true); // helper cek kolom

            for ($i = 1; $i <= 40; $i++) {
                $email = ($i === 1) ? 'wildan@sikap.test' : "mahasiswa{$i}@sikap.test";
                $nama  = ($i === 1) ? 'Wildan Nouval' : fake('id_ID')->name();

                $userMhs = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name'              => $nama,
                        'password'          => Hash::make('password'),
                        'role'              => 'Mahasiswa',
                        'email_verified_at' => $now,
                    ]
                );

                // ambil sample atribut dari factory (mungkin mengandung key yg tidak ada di DB)
                $sample = Mahasiswa::factory()->make()->toArray();

                // normalisasi: kalau factory masih pakai 'angkatan', petakan ke 'tahun_angkatan'
                if (!isset($sample['tahun_angkatan']) && isset($sample['angkatan'])) {
                    $sample['tahun_angkatan'] = $sample['angkatan'];
                    unset($sample['angkatan']);
                }

                // siapkan payload hanya untuk kolom yang memang ada
                $payload = [
                    'jurusan_id'     => $jurusanTI->id,
                    'nama_mahasiswa' => $nama,
                ];

                if ($col('nim') && isset($sample['nim'])) {
                    $payload['nim'] = $sample['nim'];
                }
                if ($col('tahun_angkatan')) {
                    $payload['tahun_angkatan'] = $sample['tahun_angkatan'] ?? rand(2019, 2024);
                }
                if ($col('no_hp') && isset($sample['no_hp'])) {
                    $payload['no_hp'] = $sample['no_hp'];
                }
                if ($col('alamat') && isset($sample['alamat'])) {
                    $payload['alamat'] = $sample['alamat'];
                }

                // simpan idempotent berbasis user_id
                $m = Mahasiswa::updateOrCreate(
                    ['user_id' => $userMhs->id],
                    $payload
                );

                $mahasiswas->push($m);
            }


            // ---- SURAT PENGANTAR ----
            $this->command?->info('Membuat data surat pengantar...');

            // daftar kolom yang benar2 ada di tabel SP
            $spCols = Schema::getColumnListing('surat_pengantars');
            $has = fn(string $k) => in_array($k, $spCols, true);

            foreach ($mahasiswas as $index => $mhs) {
                // status dasar + tanggal default
                $status = 'Disetujui';
                if ($index < 5)  $status = 'Diajukan';
                elseif ($index < 10) $status = 'Ditolak';

                // start with factory attrs
                $attrs = SuratPengantar::factory()->make()->toArray();

                // paksa status sesuai skenario
                $attrs['status_surat_pengantar'] = $status;

                // isi tanggal-tanggal WAJIB jika kolomnya ada
                // pengajuan: 20–40 hari lalu
                if ($has('tanggal_pengajuan_surat_pengantar')) {
                    $attrs['tanggal_pengajuan_surat_pengantar'] = now()->subDays(rand(20, 40));
                }
                // kalau disetujui: 5–15 hari lalu
                if ($status === 'Disetujui' && $has('tanggal_disetujui_surat_pengantar')) {
                    $attrs['tanggal_disetujui_surat_pengantar'] = now()->subDays(rand(5, 15));
                }
                // kalau siap diambil (kalau kamu pakai status ini), bisa isi tanggal diambil
                if ($status === 'Siap Diambil' && $has('tanggal_diambil_surat_pengantar')) {
                    $attrs['tanggal_diambil_surat_pengantar'] = now()->subDays(rand(1, 3));
                }
                // kalau ditolak: isi catatan (jika kolom ada)
                if ($status === 'Ditolak' && $has('catatan_surat')) {
                    $attrs['catatan_surat'] = 'Tujuan instansi tidak relevan. Harap ajukan yang baru.';
                }

                // pastikan nomor_surat ada; kalau template factory kosong, kasih pola default
                if ($has('nomor_surat') && empty($attrs['nomor_surat'])) {
                    $bulan = (int) now()->format('n');
                    $romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][$bulan];
                    $urut = str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT);
                    $attrs['nomor_surat'] = "FT/IF/KP/{$romawi}/".now()->format('Y')."/{$urut}";
                }

                // satu SP per mahasiswa (idempotent)
                SuratPengantar::updateOrCreate(
                    ['mahasiswa_id' => $mhs->id],
                    $attrs
                );
            }


            // ---- ALUR KP & SEMINAR ----
            $this->command?->info('Mensimulasikan alur kerja KP & Seminar...');

            // ambil daftar kolom KP sekali, supaya adaptif dengan skema
            $kpCols = Schema::getColumnListing('kerja_prakteks');
            $kpHas  = fn(string $k) => in_array($k, $kpCols, true);

            foreach ($mahasiswas as $index => $mhs) {
                // beberapa mhs baru tahap SP
                if ($index < 5) continue;

                // cari SP milik mhs ini (kalau diperlukan sebagai FK)
                $sp = SuratPengantar::where('mahasiswa_id', $mhs->id)->first();

                // siapkan atribut dasar dari factory
                $faker  = fake('id_ID');
                $kpAttrs = KerjaPraktek::factory()->make([
                    'dosen_pembimbing_id' => $dosenPembimbings->random()->id,
                ])->toArray();

                // override judul agar tidak pakai formatter asing
                $kpAttrs['judul_kp'] = 'Pengembangan '.$faker->words(3, true).' pada '.$faker->company();

                // isi kolom-kolom wajib jika ada di skema
                if ($kpHas('proposal_kp') && empty($kpAttrs['proposal_kp'])) {
                    $kpAttrs['proposal_kp'] = 'demo/proposal_kp_'.$mhs->id.'.pdf';
                }
                if ($kpHas('surat_penerimaan_kp') && empty($kpAttrs['surat_penerimaan_kp'])) {
                    $kpAttrs['surat_penerimaan_kp'] = 'demo/surat_penerimaan_'.$mhs->id.'.pdf';
                }
                if ($kpHas('surat_keterangan_kp') && empty($kpAttrs['surat_keterangan_kp'])) {
                    $kpAttrs['surat_keterangan_kp'] = 'demo/surat_keterangan_'.$mhs->id.'.pdf';
                }
                // (opsional, kalau ada di skema kamu)
                if ($kpHas('surat_balasan_kp') && empty($kpAttrs['surat_balasan_kp'])) {
                    $kpAttrs['surat_balasan_kp'] = 'demo/surat_balasan_'.$mhs->id.'.pdf';
                }
                if ($kpHas('surat_pengantar_id') && empty($kpAttrs['surat_pengantar_id']) && $sp) {
                    $kpAttrs['surat_pengantar_id'] = $sp->id;
                }

                // tanggal periode KP (kalau skema punya kolomnya)
                if ($kpHas('tanggal_mulai_kp') && empty($kpAttrs['tanggal_mulai_kp'])) {
                    $kpAttrs['tanggal_mulai_kp'] = now()->subDays(rand(45, 90))->toDateString();
                }
                if ($kpHas('tanggal_selesai_kp') && empty($kpAttrs['tanggal_selesai_kp'])) {
                    $kpAttrs['tanggal_selesai_kp'] = now()->addDays(rand(30, 60))->toDateString();
                }

                // satu KP per mahasiswa (idempotent)
                $kp = KerjaPraktek::updateOrCreate(
                    ['mahasiswa_id' => $mhs->id],
                    $kpAttrs
                );

                // status bertahap sesuai skenario
                if ($index < 10) {
                    $kp->update(['status_pengajuan_kp' => 'Diajukan']);
                    continue;
                }
                if ($index < 15) {
                    $kp->update(['status_pengajuan_kp' => 'Proses di Komisi']);
                    continue;
                }
                if ($index < 20) {
                    $kp->update(['status_pengajuan_kp' => 'Disetujui']);
                    continue;
                }

                // SPK terbit → KP berlangsung
                $kp->update(['status_pengajuan_kp' => 'SPK Terbit', 'status_kp' => 'Berlangsung']);

                // bimbingan: top-up hingga target (tanpa duplikasi)
                $target   = ($index < 25) ? rand(1, 5) : rand(6, 12);
                $existing = $kp->konsultasis()->count();
                $need     = max(0, $target - $existing);

                for ($j = 0; $j < $need; $j++) {
                    $status = ($j < $target - 2) ? 'Diverifikasi' : 'Menunggu Verifikasi';
                    Konsultasi::factory()->create([
                        'kerja_praktek_id'    => $kp->id,
                        'mahasiswa_id'        => $mhs->id,
                        'dosen_pembimbing_id' => $kp->dosen_pembimbing_id,
                        'status_verifikasi'   => $status,
                    ]);
                }

                $bimbinganVerified = $kp->konsultasis()->where('status_verifikasi', 'Diverifikasi')->count();
                if ($bimbinganVerified < 6) continue;

                // SEMINAR (adaptif juga)
                $semCols = Schema::getColumnListing('seminars');
                $semHas  = fn(string $k) => in_array($k, $semCols, true);

                $semAttrs = Seminar::factory()->make([
                    'ruangan_id' => $ruangans->random(),
                ])->toArray();

                // judul aman untuk id_ID
                $semAttrs['judul_kp_final'] = 'Implementasi '.$faker->words(3, true).' pada '.$faker->company();

                // === Guard berkas/dokumen WAJIB jika ada di skema ===
                // yang umum ditemui: berkas_laporan_final (wajib), berkas_ppt / slides, lembar_persetujuan, dll.
                if ($semHas('berkas_laporan_final') && empty($semAttrs['berkas_laporan_final'])) {
                    $semAttrs['berkas_laporan_final'] = 'demo/berkas_laporan_final_'.$mhs->id.'.pdf';
                }
                if ($semHas('berkas_ppt') && empty($semAttrs['berkas_ppt'])) {
                    $semAttrs['berkas_ppt'] = 'demo/berkas_ppt_'.$mhs->id.'.pdf';
                }
                if ($semHas('berkas_slides') && empty($semAttrs['berkas_slides'])) {
                    $semAttrs['berkas_slides'] = 'demo/berkas_slides_'.$mhs->id.'.pdf';
                }
                if ($semHas('lembar_persetujuan') && empty($semAttrs['lembar_persetujuan'])) {
                    $semAttrs['lembar_persetujuan'] = 'demo/lembar_persetujuan_'.$mhs->id.'.pdf';
                }
                if ($semHas('berita_acara_file') && empty($semAttrs['berita_acara_file'])) {
                    $semAttrs['berita_acara_file'] = 'demo/berita_acara_'.$mhs->id.'.pdf';
                }

                // Simpan idempotent
                $seminar = Seminar::updateOrCreate(
                    ['kerja_praktek_id' => $kp->id],
                    $semAttrs
                );

                // Status sesuai skenario
                if ($index < 30) {
                    $seminar->update(['status_seminar' => 'Diajukan']);
                } elseif ($index < 35) {
                    $seminar->update(['status_seminar' => 'Dijadwalkan']);
                } else {
                    $seminar->update([
                        'status_seminar'            => 'Dinilai',
                        'nilai_pembimbing_lapangan' => rand(75, 95),
                        'nilai_dosen_pembimbing'    => rand(78, 98),
                        'nilai_akhir'               => ['A', 'AB', 'B', 'BC'][rand(0, 3)],
                    ]);
                    $kp->update(['status_kp' => 'Selesai']);
                }
            }

            $this->command?->info('Done ✅');
        });
    }
}
