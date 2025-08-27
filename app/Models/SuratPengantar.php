<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SuratPengantar extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Kolom yang diisi Mahasiswa saat pembuatan
        'mahasiswa_id',
        'lokasi_surat_pengantar',
        'penerima_surat_pengantar',
        'alamat_surat_pengantar',
        'tembusan_surat_pengantar',
        'tanggal_pengajuan_surat_pengantar',
        'status_surat_pengantar',
        'catatan_surat',
        'tanggal_disetujui_surat_pengantar',
        'tanggal_pengambilan_surat_pengantar',
        'nomor_surat',
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

    /**
     * Get the mahasiswa that owns the surat pengantar.
     */
    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}
