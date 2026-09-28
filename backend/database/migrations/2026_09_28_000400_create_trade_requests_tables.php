<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 255);
            $table->string('recipient', 150)->nullable();
            $table->date('needed_date')->nullable();
            $table->string('purchase_ticket_no', 80)->nullable();
            $table->string('flow', 20)->default('trade')->index();
            $table->boolean('needs_purchase')->default(false);
            $table->string('status', 35)->default('rascunho')->index();
            $table->decimal('total_value', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty_requested');
            $table->unsignedInteger('qty_approved')->nullable();
            $table->unsignedInteger('qty_reserved')->default(0);
            $table->unsignedInteger('qty_delivered')->default(0);
            $table->decimal('unit_value', 12, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'item_id']);
        });

        Schema::create('request_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('from_status', 35)->nullable();
            $table->string('to_status', 35);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('comment', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['request_id', 'created_at']);
        });

        Schema::create('request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20)->default('pendente')->index();
            $table->text('justification')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
        Schema::dropIfExists('request_status_history');
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
    }
};
