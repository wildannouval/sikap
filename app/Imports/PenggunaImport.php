<?php

namespace App\Imports;

use App\Models\Dosen;
use App\Models\Jurusan;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PenggunaImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $jurusan = Jurusan::where('kode_jurusan', $row['kode_jurusan'])->first();
                if (!$jurusan) {
                    // Lewati baris jika jurusan tidak ditemukan
                    continue;
                }

                $role = ($row['peran'] === 'Dosen') ?
                    ($row['is_komisi'] == 1 ? 'Dosen Komisi' : 'Dosen Pembimbing') : 'Mahasiswa';

                $user = User::create([
                    'name'     => $row['nama'],
                    'email'    => $row['email'],
                    'password' => Hash::make($row['password']),
                    'role'     => $role,
                ]);

                if ($row['peran'] === 'Mahasiswa') {
                    Mahasiswa::create([
                        'user_id' => $user->id,
                        'jurusan_id' => $jurusan->id,
                        'nama_mahasiswa' => $row['nama'],
                        'nim' => $row['nim_nip'],
                        'tahun_angkatan' => $row['tahun_angkatan'],
                    ]);
                } elseif ($row['peran'] === 'Dosen') {
                    Dosen::create([
                        'user_id' => $user->id,
                        'jurusan_id' => $jurusan->id,
                        'nama_dosen' => $row['nama'],
                        'nip' => $row['nim_nip'],
                        'is_komisi' => $row['is_komisi'] == 1,
                    ]);
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            '*.nama' => 'required|string',
            '*.email' => 'required|email|unique:users,email',
            '*.password' => 'required|min:8',
            '*.peran' => 'required|in:Mahasiswa,Dosen',
            '*.kode_jurusan' => 'required|exists:jurusans,kode_jurusan',
            '*.nim_nip' => 'required|string',
            '*.tahun_angkatan' => 'nullable|required_if:peran,Mahasiswa|digits:4',
            '*.is_komisi' => 'nullable|required_if:peran,Dosen|boolean',
        ];
    }

    /**
     * Fungsi BARU untuk memaksa kolom dibaca sebagai teks.
     */
    public function columnFormats(): array
    {
        return [
            // 'F' adalah representasi untuk kolom ke-6 (nim_nip)
            'F' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
