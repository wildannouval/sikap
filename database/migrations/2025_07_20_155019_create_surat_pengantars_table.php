<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('surat_pengantars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained();
            $table->string('lokasi_surat_pengantar');
            $table->string('penerima_surat_pengantar');
            $table->text('alamat_surat_pengantar');
            $table->string('tembusan_surat_pengantar')->nullable();
            $table->enum('status_surat_pengantar', [
                'Diajukan',
                'Disetujui',
                'Ditolak',
            ])->default('Diajukan');
            $table->date('tanggal_pengajuan_surat_pengantar');
            $table->date('tanggal_disetujui_surat_pengantar')->nullable();
            $table->date('tanggal_pengambilan_surat_pengantar')->nullable();
            $table->text('catatan_surat')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_pengantars');
    }
};
