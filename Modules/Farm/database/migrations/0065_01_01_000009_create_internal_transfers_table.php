<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/§3 ⭐⭐/BR-OPS-03-008/009 — closes the Book F
 * interface. Never a free transfer: always at internal cost, always a
 * real journal, always a real `FIN-09` stock movement between the
 * farm store and the kitchen store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_transfers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('transfer_number', 40);
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->foreignId('from_store_id')->constrained('stores');
            $table->foreignId('to_store_id')->constrained('stores');
            $table->date('transfer_date');
            $table->foreignId('harvest_id')->nullable()->constrained('harvests');
            $table->foreignId('output_id')->nullable()->constrained('production_outputs');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20);
            $table->bigInteger('unit_cost_minor');
            $table->bigInteger('total_cost_minor');
            $table->char('currency', 3);
            $table->bigInteger('market_price_minor')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('dispatched_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->string('status', 20);

            $table->unique(['school_id', 'transfer_number'], 'internal_transfers_school_number_unique');
            $table->index(['school_id', 'term_id', 'transfer_date'], 'internal_transfers_term_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_transfers');
    }
};
