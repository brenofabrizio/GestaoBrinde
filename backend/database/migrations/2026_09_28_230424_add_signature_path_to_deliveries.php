<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->string('signature_path')->nullable()->after('signature_hash');
            $table->string('idempotency_payload_hash', 64)->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropColumn('signature_path');
        });
    }
};
