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
            // Ubah nama kolom nilai_seminar menjadi nilai_akhir
            $table->renameColumn('nilai_seminar', 'nilai_akhir');

            // Tambahkan dua kolom baru untuk komponen nilai
            $table->string('nilai_pembimbing_lapangan', 5)->nullable()->after('nilai_akhir');
            $table->string('nilai_dosen_pembimbing', 5)->nullable()->after('nilai_pembimbing_lapangan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            $table->dropColumn(['nilai_pembimbing_lapangan', 'nilai_dosen_pembimbing']);
            $table->renameColumn('nilai_akhir', 'nilai_seminar');
        });
    }
};
