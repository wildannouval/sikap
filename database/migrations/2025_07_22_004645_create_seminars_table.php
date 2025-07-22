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
        Schema::create('seminars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kerja_praktek_id')->constrained()->onDelete('cascade');
            $table->foreignId('ruangan_id')->constrained();

            $table->string('judul_kp_final');
            $table->string('berkas_laporan_final'); // Path file laporan akhir
            $table->date('tanggal_seminar');
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->enum('status_seminar', [
                'Diajukan',
                'Dijadwalkan',
                'Selesai',
                'Dinilai',
                'Ditolak'
            ])->default('Diajukan');

            $table->string('berita_acara_signed')->nullable(); // Path file berita acara yg sudah ttd
            $table->date('tanggal_pengambilan_berita_acara')->nullable();
            $table->string('nilai_seminar', 5)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seminars');
    }
};
