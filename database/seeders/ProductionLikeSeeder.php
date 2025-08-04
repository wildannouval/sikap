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
            $jurusanTE = Jurusan::create(['kode_jurusan' => 'TE', 'nama_jurusan' => 'Teknik Elektro']);
            $ruangan1 = Ruangan::create(['nama_ruangan' => 'Ruang Seminar F1', 'lokasi_gedung' => 'Gedung F']);
            $ruangan2 = Ruangan::create(['nama_ruangan' => 'Ruang Rapat Dekanat', 'lokasi_gedung' => 'Gedung A']);
            $ruangan3 = Ruangan::create(['nama_ruangan' => 'Aula Gedung C', 'lokasi_gedung' => 'Gedung C']);
            $ruangans = Ruangan::all();

            $this->command->info('Membuat akun statis...');
            User::create(['name' => 'Admin Bapendik', 'email' => 'bapendik@sikap.test', 'password' => Hash::make('password'), 'role' => 'Bapendik']);
            $dosenKomisiUser = User::create(['name' => 'Prof. Dr. Dosen Komisi', 'email' => 'doskom@sikap.test', 'password' => Hash::make('password'), 'role' => 'Dosen Komisi']);
            Dosen::create(['user_id' => $dosenKomisiUser->id, 'jurusan_id' => $jurusanTI->id, 'nama_dosen' => $dosenKomisiUser->name, 'nip' => '198001012005011001', 'is_komisi' => true]);

            $this->command->info('Membuat data dosen...');
            $dosenPembimbings = collect();
            for ($i = 1; $i <= 10; $i++) {
                $user = User::factory()->create(['email' => "dosen{$i}@sikap.test", 'role' => 'Dosen Pembimbing']);
                $dosen = Dosen::factory()->create(['user_id' => $user->id, 'jurusan_id' => $jurusanTI->id, 'nama_dosen' => $user->name]);
                $dosenPembimbings->push($dosen);
            }

            $this->command->info('Membuat data mahasiswa...');
            $mahasiswas = collect();
            for ($i = 1; $i <= 40; $i++) {
                $email = ($i == 1) ? "wildan@sikap.test" : "mahasiswa{$i}@sikap.test";
                $userMhs = User::factory()->create(['email' => $email, 'role' => 'Mahasiswa', 'name' => ($i == 1) ? 'Wildan Nouval' : fake('id_ID')->name()]);
                $mahasiswa = Mahasiswa::factory()->create(['user_id' => $userMhs->id, 'jurusan_id' => $jurusanTI->id, 'nama_mahasiswa' => $userMhs->name]);
                $mahasiswas->push($mahasiswa);
            }

            $this->command->info('Membuat data surat pengantar...');
            foreach ($mahasiswas as $index => $mahasiswa) {
                if ($index < 5) SuratPengantar::factory()->create(['mahasiswa_id' => $mahasiswa->id, 'status_surat_pengantar' => 'Diajukan']);
                elseif ($index < 10) SuratPengantar::factory()->create(['mahasiswa_id' => $mahasiswa->id, 'status_surat_pengantar' => 'Ditolak', 'catatan_surat' => 'Tujuan instansi tidak relevan. Harap ajukan yang baru.']);
                else SuratPengantar::factory()->create(['mahasiswa_id' => $mahasiswa->id, 'status_surat_pengantar' => 'Disetujui']);
            }

            $this->command->info('Mensimulasikan alur kerja KP & Seminar...');
            foreach ($mahasiswas as $index => $mahasiswa) {
                if ($index < 5) continue;

                $kp = KerjaPraktek::factory()->create(['mahasiswa_id' => $mahasiswa->id, 'dosen_pembimbing_id' => $dosenPembimbings->random()->id]);

                if ($index < 10) { $kp->update(['status_pengajuan_kp' => 'Diajukan']); continue; }
                if ($index < 15) { $kp->update(['status_pengajuan_kp' => 'Proses di Komisi']); continue; }
                if ($index < 20) { $kp->update(['status_pengajuan_kp' => 'Disetujui']); continue; }

                $kp->update(['status_pengajuan_kp' => 'SPK Terbit', 'status_kp' => 'Berlangsung']);

                $jumlahBimbingan = ($index < 25) ? rand(1, 5) : rand(6, 12);
                for ($j = 0; $j < $jumlahBimbingan; $j++) {
                    Konsultasi::factory()->create([
                        'kerja_praktek_id' => $kp->id, 'mahasiswa_id' => $mahasiswa->id, 'dosen_pembimbing_id' => $kp->dosen_pembimbing_id,
                        'status_verifikasi' => ($j < $jumlahBimbingan - 2) ? 'Diverifikasi' : 'Menunggu Verifikasi',
                    ]);
                }

                $bimbinganVerified = $kp->konsultasis()->where('status_verifikasi', 'Diverifikasi')->count();
                if ($bimbinganVerified < 6) continue;

                $seminar = Seminar::factory()->create(['kerja_praktek_id' => $kp->id, 'ruangan_id' => $ruangans->random()->id]);

                if ($index < 30) $seminar->update(['status_seminar' => 'Diajukan']);
                elseif ($index < 35) $seminar->update(['status_seminar' => 'Dijadwalkan']);
                else {
                    $seminar->update([
                        'status_seminar' => 'Dinilai', 'nilai_pembimbing_lapangan' => rand(75, 95),
                        'nilai_dosen_pembimbing' => rand(78, 98), 'nilai_akhir' => ['A', 'AB', 'B', 'BC'][rand(0, 3)],
                    ]);
                    $kp->update(['status_kp' => 'Selesai']);
                }
            }
        });
    }
}
