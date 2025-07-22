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
        Schema::create('konsultasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kerja_praktek_id')->constrained()->onDelete('cascade');
            $table->foreignId('mahasiswa_id')->constrained()->onDelete('cascade');
            $table->foreignId('dosen_pembimbing_id')->constrained('dosens');

            $table->date('tanggal_konsultasi');
            $table->text('topik_konsultasi');

            $table->enum('status_verifikasi', ['Menunggu Verifikasi', 'Diverifikasi', 'Revisi'])->default('Menunggu Verifikasi');
            $table->date('tanggal_verifikasi')->nullable();
            $table->text('catatan_konsultasi')->nullable(); // Catatan dari Dosen

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('konsultasis');
    }
};
