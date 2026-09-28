<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('delivery_id')->nullable()->after('request_id')->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->after('delivery_id')->constrained()->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('industry_id');
            $table->dropConstrainedForeignId('event_id');
            $table->dropConstrainedForeignId('delivery_id');
        });
    }
};
