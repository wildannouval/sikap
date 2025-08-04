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
        Schema::table('surat_pengantars', function (Blueprint $table) {
            // Ubah menjadi string dan tambahkan status baru
            $table->string('status_surat_pengantar')->default('Diajukan')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_pengantars', function (Blueprint $table) {
            //
        });
    }
};
