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
        Schema::table('seminars', function (Blueprint $table) {
            // Ubah tipe kolom agar bisa menyimpan angka desimal (misal: 85.50)
            $table->decimal('nilai_pembimbing_lapangan', 5, 2)->nullable()->change();
            $table->decimal('nilai_dosen_pembimbing', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            // Kembalikan ke tipe string jika di-rollback
            $table->string('nilai_pembimbing_lapangan', 5)->nullable()->change();
            $table->string('nilai_dosen_pembimbing', 5)->nullable()->change();
        });
    }
};
