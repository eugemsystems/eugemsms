<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-001 ⭐ — APPEND-ONLY, the source of
 * truth. See `Modules\Core\Models\FinancialAuditLogEntry` for why the
 * real DB-grant REVOKE is a deployment step, not a migration; the
 * model-level guard is what's tested here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots');
            $table->string('movement_type', 30);
            $table->char('direction', 3);
            $table->decimal('quantity', 14, 4);
            $table->bigInteger('unit_cost_minor');
            $table->bigInteger('total_cost_minor');
            $table->string('currency', 3);
            $table->bigInteger('base_total_minor');
            $table->decimal('balance_after', 14, 4);
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres');
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->string('reference', 120)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('occurred_at', 6);

            $table->index(['school_id', 'store_id', 'item_id', 'occurred_at'], 'stock_movements_ledger_idx');
            $table->index(['school_id', 'term_id', 'movement_type'], 'stock_movements_term_type_idx');
            $table->index(['source_type', 'source_id'], 'stock_movements_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
