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

class ProductionLikeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // === 1. PERSIAPAN DATA MASTER ===
            $this->command->info('Menyiapkan data master...');
            $jurusanTI = Jurusan::create(['kode_jurusan' => 'IF', 'nama_jurusan' => 'Teknik Informatika']);
            $jurusanSI = Jurusan::create(['kode_jurusan' => 'SI', 'nama_jurusan' => 'Sistem Informasi']);
            $ruangan1 = Ruangan::create(['nama_ruangan' => 'Ruang Seminar A', 'lokasi_gedung' => 'Gedung F']);
            $ruangan2 = Ruangan::create(['nama_ruangan' => 'Ruang Rapat B', 'lokasi_gedung' => 'Gedung F']);

            // === 2. MEMBUAT AKUN STATIS UNTUK LOGIN ===
            $this->command->info('Membuat akun statis...');
            User::create(['name' => 'Bapendik SIKAP', 'email' => 'bapendik@sikap.test', 'password' => Hash::make('password'), 'role' => 'Bapendik']);

            // Dosen Komisi Statis
            $dosenKomisiUser = User::create(['name' => 'Dosen Komisi', 'email' => 'doskom@sikap.test', 'password' => Hash::make('password'), 'role' => 'Dosen Komisi']);
            Dosen::create(['user_id' => $dosenKomisiUser->id, 'jurusan_id' => $jurusanTI->id, 'nama_dosen' => $dosenKomisiUser->name, 'nip' => '198001012005011001', 'is_komisi' => true]);

            // === 3. MEMBUAT 10 DOSEN BARU ===
            $this->command->info('Membuat data dosen...');
            $dosenPembimbings = collect();
            for ($i = 1; $i <= 10; $i++) {
                $user = User::factory()->create(['email' => "dosen{$i}@sikap.test", 'role' => 'Dosen Pembimbing']);
                $dosen = Dosen::factory()->create(['user_id' => $user->id, 'jurusan_id' => $jurusanTI->id, 'nama_dosen' => $user->name]);
                $dosenPembimbings->push($dosen);
            }

            // === 4. MEMBUAT 30 MAHASISWA & SIMULASI ALUR KERJA ===
            $this->command->info('Membuat data mahasiswa dan mensimulasikan alur kerja...');
            for ($i = 1; $i <= 30; $i++) {
                $userMhs = User::factory()->create(['email' => "mahasiswa{$i}@sikap.test", 'role' => 'Mahasiswa']);
                $mahasiswa = Mahasiswa::factory()->create(['user_id' => $userMhs->id, 'jurusan_id' => $jurusanTI->id, 'nama_mahasiswa' => $userMhs->name]);

                // Tentukan secara acak progres mahasiswa
                $progress = rand(1, 100);

                // Buat Surat Pengantar untuk hampir semua mahasiswa
                if ($progress > 5) {
                    SuratPengantar::factory()->create(['mahasiswa_id' => $mahasiswa->id, 'status_surat_pengantar' => 'Disetujui']);
                }

                // Jika progres > 15, mahasiswa mengajukan KP
                if ($progress > 15) {
                    $kp = KerjaPraktek::factory()->create([
                        'mahasiswa_id' => $mahasiswa->id,
                        'dosen_pembimbing_id' => $dosenPembimbings->random()->id,
                    ]);

                    // Tentukan status KP secara acak
                    if ($progress <= 30) $kp->update(['status_pengajuan_kp' => 'Diajukan']);
                    elseif ($progress <= 40) $kp->update(['status_pengajuan_kp' => 'Proses di Komisi']);
                    elseif ($progress <= 50) $kp->update(['status_pengajuan_kp' => 'Ditolak']);
                    else {
                        $kp->update(['status_pengajuan_kp' => 'SPK Terbit', 'status_kp' => 'Berlangsung']);

                        // Jika KP sudah berjalan, buat data bimbingan
                        $jumlahBimbingan = rand(1, 10);
                        for ($j = 0; $j < $jumlahBimbingan; $j++) {
                            Konsultasi::factory()->create([
                                'kerja_praktek_id' => $kp->id,
                                'mahasiswa_id' => $mahasiswa->id,
                                'dosen_pembimbing_id' => $kp->dosen_pembimbing_id,
                                'status_verifikasi' => (rand(1, 10) > 3) ? 'Diverifikasi' : 'Menunggu Verifikasi',
                            ]);
                        }

                        // Jika progres > 80 & bimbingan cukup, mahasiswa daftar seminar
                        $bimbinganVerified = $kp->konsultasis()->where('status_verifikasi', 'Diverifikasi')->count();
                        if ($progress > 80 && $bimbinganVerified >= 6) {
                            $seminar = Seminar::factory()->create([
                                'kerja_praktek_id' => $kp->id,
                                'ruangan_id' => (rand(0, 1) ? $ruangan1->id : $ruangan2->id),
                            ]);

                            // Tentukan status seminar secara acak
                            if ($progress <= 85) $seminar->update(['status_seminar' => 'Diajukan']);
                            elseif ($progress <= 95) $seminar->update(['status_seminar' => 'Dijadwalkan']);
                            else {
                                $seminar->update(['status_seminar' => 'Dinilai', 'nilai_seminar' => ['A', 'A-', 'B+', 'B', 'B-'][rand(0, 4)]]);
                                $kp->update(['status_kp' => 'Selesai']);
                            }
                        }
                    }
                }
            }
        });
    }
}
