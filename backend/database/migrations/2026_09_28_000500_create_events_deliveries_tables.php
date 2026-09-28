<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('venue', 190)->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('planejado')->index();
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty_allocated')->default(0);
            $table->unsignedInteger('qty_withdrawn')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'industry_id', 'item_id']);
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 20)->default('dia_a_dia');
            $table->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivered_by')->constrained('users')->restrictOnDelete();
            $table->string('received_by_name', 150);
            $table->string('received_by_document', 40)->nullable();
            $table->string('received_by_email')->nullable();
            $table->string('received_by_phone', 40)->nullable();
            $table->string('signature_hash', 64)->nullable();
            $table->string('verification_hash', 64)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->integer('balance_after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('event_allocations');
        Schema::dropIfExists('events');
    }
};
