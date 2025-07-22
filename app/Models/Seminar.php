<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'nilai_seminar',
        'catatan',
    ];

    public function kerjaPraktek(): BelongsTo
    {
        return $this->belongsTo(KerjaPraktek::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }
}
