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
        Schema::table('kerja_prakteks', function (Blueprint $table) {
            $table->dropForeign(['surat_pengantar_id']);

            $table->dropColumn([
                'surat_pengantar_id',
                'tanggal_mulai_kp',
                'tanggal_selesai_kp',
                'tanggal_penilaian_kp',
                'nilai_seminar_kp',
                'tanggal_pengambilan_spk', // <-- [TAMBAHAN] Kolom ini sekarang ikut dihapus
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kerja_prakteks', function (Blueprint $table) {
            $table->foreignId('surat_pengantar_id')->nullable()->constrained('surat_pengantars');
            $table->date('tanggal_mulai_kp')->nullable();
            $table->date('tanggal_selesai_kp')->nullable();
            $table->date('tanggal_penilaian_kp')->nullable();
            $table->string('nilai_seminar_kp', 5)->nullable();
            $table->date('tanggal_pengambilan_spk')->nullable(); // <-- [TAMBAHAN] Kolom ini akan dibuat kembali jika migrasi di-rollback
        });
    }
};