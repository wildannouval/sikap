<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distribusi extends Model
{
    use HasFactory;

    protected $fillable = [
        'kerja_praktek_id',
        'mahasiswa_id',
        'berkas_distribusi',
        'tanggal_distribusi',
    ];
}
