<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $targets = [
        'surat_pengantars',
        'kerja_prakteks',
        'seminars',
    ];

    public function up(): void
    {
        foreach ($this->targets as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'uuid')) $t->uuid('uuid')->nullable()->after('id')->index();
                if (!Schema::hasColumn($table, 'qr_token')) $t->string('qr_token', 191)->nullable()->index();
                if (!Schema::hasColumn($table, 'qr_expires_at')) $t->timestamp('qr_expires_at')->nullable();
                if (!Schema::hasColumn($table, 'ttd_signed_at')) $t->timestamp('ttd_signed_at')->nullable();
                if (!Schema::hasColumn($table, 'ttd_signed_by')) $t->string('ttd_signed_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['uuid','qr_token','qr_expires_at','ttd_signed_at','ttd_signed_by'] as $col) {
                    if (Schema::hasColumn($table, $col)) $t->dropColumn($col);
                }
            });
        }
    }
};
