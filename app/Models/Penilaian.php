<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penilaian extends Model
{
    protected $fillable = ['seminar_id', 'nama_komponen', 'nilai', 'tipe'];
}
