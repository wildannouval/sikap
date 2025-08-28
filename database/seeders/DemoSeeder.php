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

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now   = now();
            $faker = fake('id_ID');

            // ===== Master data =====
            Jurusan::upsert([
                ['kode_jurusan' => 'IF', 'nama_jurusan' => 'Informatika', 'created_at' => $now, 'updated_at' => $now],
            ], ['kode_jurusan'], ['nama_jurusan', 'updated_at']);

            $jurIF = Jurusan::where('kode_jurusan', 'IF')->first();

            Ruangan::upsert([
                ['nama_ruangan' => 'Ruang Demo KP', 'lokasi_gedung' => 'Gedung F', 'created_at'=>$now,'updated_at'=>$now],
                ['nama_ruangan' => 'Ruang Demo A',  'lokasi_gedung' => 'Gedung A', 'created_at'=>$now,'updated_at'=>$now],
                ['nama_ruangan' => 'Ruang Demo C',  'lokasi_gedung' => 'Gedung C', 'created_at'=>$now,'updated_at'=>$now],
            ], ['nama_ruangan'], ['lokasi_gedung', 'updated_at']);

            $ruangIds = Ruangan::whereIn('nama_ruangan', ['Ruang Demo KP','Ruang Demo A','Ruang Demo C'])->pluck('id');

            // Dosen Pembimbing demo khusus
            $dospemUser = User::updateOrCreate(
                ['email' => 'demo.dospem@sikap.test'],
                [
                    'name' => 'Dosen Pembimbing Demo',
                    'password' => Hash::make('password'),
                    'role' => 'Dosen Pembimbing',
                    'email_verified_at' => $now,
                ]
            );
            $dospemDemo = Dosen::updateOrCreate(
                ['user_id' => $dospemUser->id],
                [
                    'jurusan_id' => $jurIF->id,
                    'nama_dosen' => $dospemUser->name,
                    'nip'        => '199001012010011999',
                    'is_komisi'  => false,
                ]
            );

            // Helpers adaptif skema
            $mhCols = Schema::getColumnListing('mahasiswas'); $hasMh = fn($k)=>in_array($k,$mhCols,true);
            $spCols = Schema::getColumnListing('surat_pengantars'); $hasSp = fn($k)=>in_array($k,$spCols,true);
            $kpCols = Schema::getColumnListing('kerja_prakteks'); $hasKp = fn($k)=>in_array($k,$kpCols,true);
            $smCols = Schema::getColumnListing('seminars'); $hasSm = fn($k)=>in_array($k,$smCols,true);

            // ========= A. DEMO PROGRESS (Dijadwalkan) =========
            $progressUser = User::updateOrCreate(
                ['email' => 'demo@sikap.test'],
                [
                    'name' => 'Akun Demo Mahasiswa',
                    'password' => Hash::make('password'),
                    'role' => 'Mahasiswa',
                    'email_verified_at' => $now,
                ]
            );

            $progressMhsPayload = [
                'jurusan_id'     => $jurIF->id,
                'nama_mahasiswa' => $progressUser->name,
            ];
            if ($hasMh('nim'))            $progressMhsPayload['nim'] = 'G1A000DEMO';
            if ($hasMh('tahun_angkatan')) $progressMhsPayload['tahun_angkatan'] = 2022;
            if ($hasMh('no_hp'))          $progressMhsPayload['no_hp'] = '081234567890';
            if ($hasMh('alamat'))         $progressMhsPayload['alamat'] = 'Jalan Contoh No. 1, Kota Demo';

            $progressMhs = Mahasiswa::updateOrCreate(['user_id'=>$progressUser->id], $progressMhsPayload);

            $romawi = ['', 'I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][(int)now()->format('n')];
            $noSurat = "FT/IF/KP/{$romawi}/".now()->format('Y')."/".str_pad((string)rand(1,999),3,'0',STR_PAD_LEFT);

            $progressSpPayload = [
                'status_surat_pengantar'   => 'Disetujui',
                'nomor_surat'              => $noSurat,
                'penerima_surat_pengantar' => $faker->company(),
                'alamat_surat_pengantar'   => $faker->address(),
                'lokasi_surat_pengantar'   => $faker->city(),
                'tembusan_surat_pengantar' => 'Arsip, Bapendik',
            ];
            if ($hasSp('tanggal_pengajuan_surat_pengantar')) $progressSpPayload['tanggal_pengajuan_surat_pengantar'] = now()->subDays(14);
            if ($hasSp('tanggal_disetujui_surat_pengantar')) $progressSpPayload['tanggal_disetujui_surat_pengantar'] = now()->subDays(10);
            if ($hasSp('tanggal_diambil_surat_pengantar'))   $progressSpPayload['tanggal_diambil_surat_pengantar']   = now()->subDays(7);

            $progressSp = SuratPengantar::updateOrCreate(['mahasiswa_id'=>$progressMhs->id], $progressSpPayload);

            $progressKpPayload = [
                'judul_kp'             => 'Pengembangan '.$faker->words(3, true).' pada '.$faker->company(),
                'lokasi_kp'            => $faker->city(),
                'status_pengajuan_kp'  => 'SPK Terbit',
                'status_kp'            => 'Berlangsung',
                'tanggal_pengajuan_kp' => now()->subDays(20),
                'tanggal_disetujui_kp' => now()->subDays(15),
                'tanggal_disetujui_spk'=> now()->subDays(5),
                'nomor_spk'            => 'SPK/'.now()->format('Y').'/'.str_pad((string)rand(1,999),3,'0',STR_PAD_LEFT),
                'dosen_pembimbing_id'  => $dospemDemo->id,
            ];
            if ($hasKp('proposal_kp'))         $progressKpPayload['proposal_kp'] = 'demo/proposal_kp_progress.pdf';
            if ($hasKp('surat_penerimaan_kp')) $progressKpPayload['surat_penerimaan_kp'] = 'demo/surat_penerimaan_progress.pdf';
            if ($hasKp('surat_keterangan_kp')) $progressKpPayload['surat_keterangan_kp'] = 'demo/surat_keterangan_progress.pdf';
            if ($hasKp('surat_pengantar_id'))  $progressKpPayload['surat_pengantar_id'] = $progressSp->id;
            if ($hasKp('tanggal_mulai_kp'))    $progressKpPayload['tanggal_mulai_kp'] = now()->subDays(45)->toDateString();
            if ($hasKp('tanggal_selesai_kp'))  $progressKpPayload['tanggal_selesai_kp'] = now()->addDays(45)->toDateString();

            $progressKp = KerjaPraktek::updateOrCreate(['mahasiswa_id'=>$progressMhs->id], $progressKpPayload);

            // Bimbingan progress (8 entri, 6 diverifikasi)
            $target=8; $exist=$progressKp->konsultasis()->count();
            for($i=$exist; $i<$target; $i++){
                Konsultasi::factory()->create([
                    'kerja_praktek_id'    => $progressKp->id,
                    'mahasiswa_id'        => $progressMhs->id,
                    'dosen_pembimbing_id' => $progressKp->dosen_pembimbing_id,
                    'status_verifikasi'   => ($i < $target - 2) ? 'Diverifikasi' : 'Menunggu Verifikasi',
                ]);
            }

            // Seminar progress → Dijadwalkan dalam 3–7 hari
            $progressSemPayload = [
                'judul_kp_final'  => 'Implementasi '.$faker->words(3, true).' pada '.$faker->company(),
                'tanggal_seminar' => now()->addDays(rand(3,7))->toDateString(),
                'jam_mulai'       => '11:00:00',
                'jam_selesai'     => '12:30:00',
                'status_seminar'  => 'Dijadwalkan',
                'ruangan_id'      => $ruangIds->random(),
            ];
            if ($hasSm('berkas_laporan_final')) $progressSemPayload['berkas_laporan_final'] = 'demo/berkas_laporan_final_progress.pdf';
            if ($hasSm('berkas_ppt'))           $progressSemPayload['berkas_ppt'] = 'demo/berkas_ppt_progress.pdf';
            if ($hasSm('lembar_persetujuan'))   $progressSemPayload['lembar_persetujuan'] = 'demo/lembar_persetujuan_progress.pdf';

            Seminar::updateOrCreate(['kerja_praktek_id'=>$progressKp->id], $progressSemPayload);

            // ========= B. DEMO SELESAI (Dinilai + Nilai tampil) =========
            $doneUser = User::updateOrCreate(
                ['email' => 'demo.done@sikap.test'],
                [
                    'name' => 'Akun Demo Mahasiswa (Selesai)',
                    'password' => Hash::make('password'),
                    'role' => 'Mahasiswa',
                    'email_verified_at' => $now,
                ]
            );

            $doneMhsPayload = [
                'jurusan_id'     => $jurIF->id,
                'nama_mahasiswa' => $doneUser->name,
            ];
            if ($hasMh('nim'))            $doneMhsPayload['nim'] = 'G1A000DONE';
            if ($hasMh('tahun_angkatan')) $doneMhsPayload['tahun_angkatan'] = 2021;
            if ($hasMh('no_hp'))          $doneMhsPayload['no_hp'] = '081298765432';
            if ($hasMh('alamat'))         $doneMhsPayload['alamat'] = 'Jalan Selesai No. 2, Kota Demo';

            $doneMhs = Mahasiswa::updateOrCreate(['user_id'=>$doneUser->id], $doneMhsPayload);

            $doneSpPayload = [
                'status_surat_pengantar'   => 'Disetujui',
                'nomor_surat'              => "FT/IF/KP/{$romawi}/".now()->format('Y')."/".str_pad((string)rand(100,999),3,'0',STR_PAD_LEFT),
                'penerima_surat_pengantar' => $faker->company(),
                'alamat_surat_pengantar'   => $faker->address(),
                'lokasi_surat_pengantar'   => $faker->city(),
                'tembusan_surat_pengantar' => 'Arsip, Bapendik',
            ];
            if ($hasSp('tanggal_pengajuan_surat_pengantar')) $doneSpPayload['tanggal_pengajuan_surat_pengantar'] = now()->subDays(60);
            if ($hasSp('tanggal_disetujui_surat_pengantar')) $doneSpPayload['tanggal_disetujui_surat_pengantar'] = now()->subDays(55);
            if ($hasSp('tanggal_diambil_surat_pengantar'))   $doneSpPayload['tanggal_diambil_surat_pengantar']   = now()->subDays(52);

            $doneSp = SuratPengantar::updateOrCreate(['mahasiswa_id'=>$doneMhs->id], $doneSpPayload);

            $doneKpPayload = [
                'judul_kp'             => 'Optimasi '.$faker->words(3, true).' pada '.$faker->company(),
                'lokasi_kp'            => $faker->city(),
                'status_pengajuan_kp'  => 'SPK Terbit',
                'status_kp'            => 'Selesai',
                'tanggal_pengajuan_kp' => now()->subDays(120),
                'tanggal_disetujui_kp' => now()->subDays(110),
                'tanggal_disetujui_spk'=> now()->subDays(100),
                'nomor_spk'            => 'SPK/'.now()->format('Y').'/'.str_pad((string)rand(1,999),3,'0',STR_PAD_LEFT),
                'dosen_pembimbing_id'  => $dospemDemo->id,
            ];
            if ($hasKp('proposal_kp'))         $doneKpPayload['proposal_kp'] = 'demo/proposal_kp_done.pdf';
            if ($hasKp('surat_penerimaan_kp')) $doneKpPayload['surat_penerimaan_kp'] = 'demo/surat_penerimaan_done.pdf';
            if ($hasKp('surat_keterangan_kp')) $doneKpPayload['surat_keterangan_kp'] = 'demo/surat_keterangan_done.pdf';
            if ($hasKp('surat_pengantar_id'))  $doneKpPayload['surat_pengantar_id'] = $doneSp->id;
            if ($hasKp('tanggal_mulai_kp'))    $doneKpPayload['tanggal_mulai_kp'] = now()->subDays(180)->toDateString();
            if ($hasKp('tanggal_selesai_kp'))  $doneKpPayload['tanggal_selesai_kp'] = now()->subDays(60)->toDateString();

            $doneKp = KerjaPraktek::updateOrCreate(['mahasiswa_id'=>$doneMhs->id], $doneKpPayload);

            // Bimbingan diverifikasi
            $targetDone=10; $existDone=$doneKp->konsultasis()->count();
            for($i=$existDone; $i<$targetDone; $i++){
                Konsultasi::factory()->create([
                    'kerja_praktek_id'    => $doneKp->id,
                    'mahasiswa_id'        => $doneMhs->id,
                    'dosen_pembimbing_id' => $doneKp->dosen_pembimbing_id,
                    'status_verifikasi'   => 'Diverifikasi',
                ]);
            }

            // Seminar Dinilai (nilai tampil)
            $doneSemPayload = [
                'judul_kp_final'  => 'Implementasi '.$faker->words(3, true).' pada '.$faker->company(),
                'tanggal_seminar' => now()->subDays(30)->toDateString(),
                'jam_mulai'       => '09:00:00',
                'jam_selesai'     => '10:30:00',
                'status_seminar'  => 'Dinilai',
                'ruangan_id'      => $ruangIds->random(),
            ];
            if ($hasSm('berkas_laporan_final')) $doneSemPayload['berkas_laporan_final'] = 'demo/berkas_laporan_final_done.pdf';
            if ($hasSm('berkas_ppt'))           $doneSemPayload['berkas_ppt'] = 'demo/berkas_ppt_done.pdf';
            if ($hasSm('lembar_persetujuan'))   $doneSemPayload['lembar_persetujuan'] = 'demo/lembar_persetujuan_done.pdf';
            if ($hasSm('nilai_pembimbing_lapangan')) $doneSemPayload['nilai_pembimbing_lapangan'] = rand(80,95);
            if ($hasSm('nilai_dosen_pembimbing'))    $doneSemPayload['nilai_dosen_pembimbing']    = rand(82,98);
            if ($hasSm('nilai_akhir'))               $doneSemPayload['nilai_akhir']               = ['A','AB','B'][rand(0,2)];

            Seminar::updateOrCreate(['kerja_praktek_id'=>$doneKp->id], $doneSemPayload);

            // Pastikan KP selesai
            $doneKp->update(['status_kp' => 'Selesai']);

            // ========= C. RAMAIKAN KALENDER (tanpa mengubah 2 akun demo) =========
            // Lindungi KP milik dua akun demo agar tidak ketimpa
            $protectedKpIds = [$progressKp->id, $doneKp->id];

            // slot harian (3 slot)
            $slots = [
                ['mulai' => '09:00:00', 'selesai' => '10:30:00'],
                ['mulai' => '11:00:00', 'selesai' => '12:30:00'],
                ['mulai' => '13:30:00', 'selesai' => '15:00:00'],
            ];

            // tanggal: 27–31 Agustus + 1–30 September (tahun berjalan)
            $year = (int) now()->format('Y');
            $dates = [];
            for ($d=27; $d<=31; $d++) $dates[] = now()->setDate($year,8,$d)->toDateString();
            for ($d=1; $d<=30; $d++)  $dates[] = now()->setDate($year,9,$d)->toDateString();

            // ambil KPs "Berlangsung" selain 2 akun demo & yang BELUM punya seminar
            $pool = KerjaPraktek::where('status_kp','Berlangsung')
                ->whereNotIn('id', $protectedKpIds)
                ->whereDoesntHave('seminar')
                ->inRandomOrder()
                ->get();

            $poolIdx = 0;
            foreach ($dates as $tgl) {
                $seminarsToday = rand(2,3); // isi 2–3 per hari
                for ($s=0; $s<$seminarsToday; $s++) {
                    if (!isset($pool[$poolIdx])) { break 2; }
                    $kp = $pool[$poolIdx]; $poolIdx++;

                    // pilih status: future => Dijadwalkan; past (30%) => Dinilai
                    $isPast = (rand(1,100) <= 30);
                    $status = $isPast ? 'Dinilai' : 'Dijadwalkan';

                    // slot & ruangan random
                    $slot = $slots[array_rand($slots)];
                    $ruangId = $ruangIds->random();

                    // JANGAN overwrite kalau (tetap) sudah punya seminar (safety)
                    if (Seminar::where('kerja_praktek_id', $kp->id)->exists()) {
                        continue;
                    }

                    $semAttrs = [
                        'judul_kp_final'  => 'Seminar '.$faker->words(3,true).' pada '.$faker->company(),
                        'tanggal_seminar' => $tgl,
                        'jam_mulai'       => $slot['mulai'],
                        'jam_selesai'     => $slot['selesai'],
                        'status_seminar'  => $status,
                        'ruangan_id'      => $ruangId,
                    ];
                    if ($hasSm('berkas_laporan_final')) $semAttrs['berkas_laporan_final'] = 'demo/berkas_laporan_final_'.$kp->id.'.pdf';

                    if ($status === 'Dinilai') {
                        if ($hasSm('nilai_pembimbing_lapangan')) $semAttrs['nilai_pembimbing_lapangan'] = rand(78,95);
                        if ($hasSm('nilai_dosen_pembimbing'))    $semAttrs['nilai_dosen_pembimbing']    = rand(80,98);
                        if ($hasSm('nilai_akhir'))               $semAttrs['nilai_akhir']               = ['A','AB','B','BC'][rand(0,3)];
                    }

                    // Pakai create saja (bukan updateOrCreate) agar tidak menimpa apa pun
                    Seminar::create(array_merge($semAttrs, [
                        'kerja_praktek_id' => $kp->id,
                    ]));
                }
            }
        });
    }
}
