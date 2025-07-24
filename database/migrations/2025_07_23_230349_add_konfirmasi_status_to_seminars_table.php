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
            // Ubah tipe kolom agar bisa menampung status baru
            $table->string('status_seminar', 50)->default('Diajukan')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            // Kembalikan ke enum jika di-rollback (opsional)
            $table->enum('status_seminar', ['Diajukan', 'Dijadwalkan', 'Selesai', 'Dinilai', 'Ditolak'])->default('Diajukan')->change();
        });
    }
};
