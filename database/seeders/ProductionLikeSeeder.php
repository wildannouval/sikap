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
use Carbon\Carbon;

class ProductionLikeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->command->info('Menyiapkan data master...');
            $jurusanTI = Jurusan::create(['kode_jurusan' => 'IF', 'nama_jurusan' => 'Teknik Informatika']);
            $jurusanSI = Jurusan::create(['kode_jurusan' => 'SI', 'nama_jurusan' => 'Sistem Informasi']);
            $ruangan1 = Ruangan::create(['nama_ruangan' => 'Ruang Seminar A', 'lokasi_gedung' => 'Gedung F']);
            $ruangan2 = Ruangan::create(['nama_ruangan' => 'Ruang Rapat B', 'lokasi_gedung' => 'Gedung F']);

            $this->command->info('Membuat akun statis...');
            User::create(['name' => 'Bapendik SIKAP', 'email' => 'bapendik@sikap.test', 'password' => Hash::make('password'), 'role' => 'Bapendik']);

            $dosenKomisiUser = User::create(['name' => 'Prof. Dr. Dosen Komisi', 'email' => 'doskom@sikap.test', 'password' => Hash::make('password'), 'role' => 'Dosen Komisi']);
            Dosen::create(['user_id' => $dosenKomisiUser->id, 'jurusan_id' => $jurusanTI->id, 'nama_dosen' => $dosenKomisiUser->name, 'nip' => '198001012005011001', 'is_komisi' => true]);

            $this->command->info('Membuat data dosen...');
            $dosenPembimbings = collect();
            for ($i = 1; $i <= 10; $i++) {
                $user = User::factory()->create([
                    'email' => "dosen{$i}@sikap.test",
                    'role' => 'Dosen Pembimbing'
                ]);
                $dosen = Dosen::factory()->create([
                    'user_id' => $user->id,
                    'jurusan_id' => $jurusanTI->id,
                    'nama_dosen' => $user->name, // <-- Perbaikan di sini
                ]);
                $dosenPembimbings->push($dosen);
            }

            $this->command->info('Membuat data mahasiswa dan mensimulasikan alur kerja...');
            for ($i = 1; $i <= 30; $i++) {
                $userMhs = User::factory()->create([
                    'email' => "mahasiswa{$i}@sikap.test",
                    'role' => 'Mahasiswa'
                ]);
                $mahasiswa = Mahasiswa::factory()->create([
                    'user_id' => $userMhs->id,
                    'jurusan_id' => $jurusanTI->id,
                    'nama_mahasiswa' => $userMhs->name, // <-- Perbaikan di sini
                ]);

                $progress = rand(1, 100);

                if ($progress > 5) {
                    SuratPengantar::factory()->create(['mahasiswa_id' => $mahasiswa->id, 'status_surat_pengantar' => 'Disetujui']);
                }

                if ($progress > 15) {
                    $dospem = $dosenPembimbings->random();
                    $kp = KerjaPraktek::factory()->create(['mahasiswa_id' => $mahasiswa->id]);

                    $status = 'Diajukan';
                    if ($progress > 30) $status = 'Proses di Komisi';
                    if ($progress > 45) $status = 'Disetujui';
                    if ($progress > 55) {
                        $status = 'SPK Terbit';
                        $kp->update(['dosen_pembimbing_id' => $dospem->id, 'status_kp' => 'Berlangsung']);
                    }
                    if ($progress > 95) $status = 'Ditolak';

                    $kp->update(['status_pengajuan_kp' => $status]);
                    if ($status === 'Ditolak') $kp->update(['catatan_kp' => 'Proposal kurang relevan, harap diperbaiki.']);

                    if (in_array($status, ['SPK Terbit'])) {
                        $jumlahBimbingan = rand(1, 10);
                        for ($j = 0; $j < $jumlahBimbingan; $j++) {
                            Konsultasi::factory()->create([
                                'kerja_praktek_id' => $kp->id,
                                'mahasiswa_id' => $mahasiswa->id,
                                'dosen_pembimbing_id' => $dospem->id,
                                'status_verifikasi' => (rand(1, 10) > 3) ? 'Diverifikasi' : 'Menunggu Verifikasi',
                            ]);
                        }

                        $bimbinganVerified = $kp->konsultasis()->where('status_verifikasi', 'Diverifikasi')->count();
                        if ($progress > 80 && $bimbinganVerified >= 6) {
                            $seminar = Seminar::factory()->create([
                                'kerja_praktek_id' => $kp->id,
                                'ruangan_id' => (rand(0, 1) ? $ruangan1->id : $ruangan2->id),
                            ]);

                            $statusSeminar = 'Diajukan';
                            if ($progress > 85) $statusSeminar = 'Dijadwalkan';
                            if ($progress > 92) {
                                $statusSeminar = 'Dinilai';
                                $nilai_lap = rand(75, 95);
                                $nilai_dos = rand(78, 98);
                                $nilai_angka = ($nilai_lap * 0.4) + ($nilai_dos * 0.6);
                                if ($nilai_angka >= 80) $nilai_huruf = 'A';
                                elseif ($nilai_angka >= 75) $nilai_huruf = 'AB';
                                elseif ($nilai_angka >= 70) $nilai_huruf = 'B';
                                else $nilai_huruf = 'BC';

                                $seminar->update([
                                    'nilai_pembimbing_lapangan' => $nilai_lap,
                                    'nilai_dosen_pembimbing' => $nilai_dos,
                                    'nilai_akhir' => $nilai_huruf,
                                ]);
                                $kp->update(['status_kp' => 'Selesai']);
                            }
                            $seminar->update(['status_seminar' => $statusSeminar]);
                        }
                    }
                }
            }
        });
    }
}
