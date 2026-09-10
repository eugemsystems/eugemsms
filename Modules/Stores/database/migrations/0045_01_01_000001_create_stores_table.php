<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2. `inventory_account_id`/`default_expense_account_id`
 * are real FKs into `FIN-01`'s chart of accounts — every store posts
 * through the general ledger, never a shadow balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('store_type', 30);
            $table->foreignId('custodian_staff_id')->nullable()->constrained('staff');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('inventory_account_id')->constrained('accounts');
            $table->foreignId('default_expense_account_id')->constrained('accounts');
            $table->string('location', 150)->nullable();
            $table->string('costing_method', 20)->default('fifo');
            $table->boolean('requires_issue_approval')->default(false);
            $table->boolean('allows_negative_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'store_type', 'is_active'], 'stores_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
