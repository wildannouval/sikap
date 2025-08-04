<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KerjaPraktek extends Model
{
    use HasFactory;

    protected $fillable = [
        'mahasiswa_id',
        'surat_pengantar_id',
        'dosen_pembimbing_id',
        'judul_kp',
        'lokasi_kp',
        'proposal_kp',
        'surat_keterangan_kp',
        'tanggal_pengajuan_kp',
        'tanggal_disetujui_kp',
        'tanggal_mulai_kp',
        'tanggal_selesai_kp',
        'status_pengajuan_kp',
        'status_kp',
        'catatan_kp',
        'tanggal_disetujui_spk',
        'tanggal_pengambilan_spk',
        'tanggal_penilaian_kp',
        'nilai_seminar_kp',
        'nomor_spk',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function dosenPembimbing(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_id');
    }

    public function suratPengantar(): BelongsTo
    {
        return $this->belongsTo(SuratPengantar::class);
    }

    // Di dalam class KerjaPraktek
    public function konsultasis()
    {
        return $this->hasMany(Konsultasi::class);
    }

    // Di dalam class KerjaPraktek
    public function seminar()
    {
        return $this->hasOne(Seminar::class);
    }
    // Di dalam class KerjaPraktek
    public function distribusi()
    {
        return $this->hasOne(Distribusi::class);
    }
}
