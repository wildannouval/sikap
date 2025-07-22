<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Konsultasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'kerja_praktek_id',
        'mahasiswa_id',
        'dosen_pembimbing_id',
        'tanggal_konsultasi',
        'topik_konsultasi',
        'status_verifikasi',
        'tanggal_verifikasi',
        'catatan_konsultasi',
    ];

    public function kerjaPraktek(): BelongsTo
    {
        return $this->belongsTo(KerjaPraktek::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function dosenPembimbing(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_id');
    }
}
