<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('industries', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('cnpj', 20)->nullable()->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 30)->default('cd')->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('cnpj', 20)->nullable()->index();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained()->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->boolean('active')->default(true)->after('password');
            $table->boolean('must_change_password')->default(false)->after('active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30)->default('fisico');
            $table->decimal('unit_value', 12, 2)->nullable();
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('status', 20)->default('ativo')->index();
            $table->date('entry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock', function (Blueprint $table): void {
            $table->foreignId('item_id')->primary()->constrained()->cascadeOnDelete();
            $table->integer('qty_on_hand')->default(0);
            $table->integer('qty_reserved')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->integer('qty');
            $table->integer('balance_after');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_ref')->nullable();
            $table->unsignedBigInteger('request_id')->nullable()->index();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->timestamps();
            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock');
        Schema::dropIfExists('items');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('industry_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['active', 'must_change_password']);
        });
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('roles');
    }
};
