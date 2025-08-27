<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Seminar extends Model
{
    use HasFactory;

    protected $fillable = [
        'kerja_praktek_id',
        'ruangan_id',
        'judul_kp_final',
        'berkas_laporan_final',
        'tanggal_seminar',
        'jam_mulai',
        'jam_selesai',
        'status_seminar',
        'berita_acara_signed',
        'tanggal_pengambilan_berita_acara',
        'nilai_akhir', // Ganti dari nilai_seminar
        'nilai_pembimbing_lapangan', // Tambahkan ini
        'nilai_dosen_pembimbing', // Tambahkan ini
        'catatan',
        'uuid','qr_token','qr_expires_at','ttd_signed_at','ttd_signed_by',
    ];

    protected $casts = [
    'qr_expires_at' => 'datetime',
    'ttd_signed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->uuid)) $m->uuid = (string) Str::uuid();
        });
    }

    public function kerjaPraktek(): BelongsTo
    {
        return $this->belongsTo(KerjaPraktek::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    public function penilaians()
    {
        return $this->hasMany(Penilaian::class);
    }
}
