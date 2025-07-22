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
        Schema::create('kerja_prakteks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained()->onDelete('cascade');
            $table->foreignId('surat_pengantar_id')->nullable()->constrained();
            $table->foreignId('dosen_pembimbing_id')->nullable()->constrained('dosens');

            $table->string('judul_kp');
            $table->string('lokasi_kp');
            $table->string('proposal_kp'); // Path ke file proposal
            $table->string('surat_keterangan_kp'); // Path ke file surat keterangan diterima

            $table->date('tanggal_pengajuan_kp');
            $table->date('tanggal_disetujui_kp')->nullable();
            $table->date('tanggal_mulai_kp')->nullable();
            $table->date('tanggal_selesai_kp')->nullable();

            $table->enum('status_pengajuan_kp', [
                'Diajukan',
                'Proses di Komisi', // Status baru setelah review Bapendik
                'Disetujui',
                'Ditolak',
                'SPK Terbit'
            ])->default('Diajukan');
            $table->enum('status_kp', ['Berlangsung', 'Selesai', 'Batal'])->nullable();

            $table->text('catatan_kp')->nullable();

            // Kolom untuk SPK (Surat Perintah Kerja)
            $table->date('tanggal_disetujui_spk')->nullable();
            $table->date('tanggal_pengambilan_spk')->nullable();

            // Kolom untuk Penilaian
            $table->date('tanggal_penilaian_kp')->nullable();
            $table->string('nilai_seminar_kp', 5)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kerja_prakteks');
    }
};
