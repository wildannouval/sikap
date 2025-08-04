<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribusi extends Model
{
    use HasFactory;

    protected $fillable = [
        'kerja_praktek_id',
        'mahasiswa_id',
        'berkas_distribusi',
        'tanggal_distribusi',
    ];

    /**
     * Mendefinisikan relasi bahwa satu data distribusi dimiliki oleh satu Mahasiswa.
     */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    /**
     * Mendefinisikan relasi bahwa satu data distribusi dimiliki oleh satu KerjaPraktek.
     */
    public function kerjaPraktek(): BelongsTo
    {
        return $this->belongsTo(KerjaPraktek::class);
    }
}
