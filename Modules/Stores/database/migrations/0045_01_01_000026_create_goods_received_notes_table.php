<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-012/013/014. `journal_id` is the
 * `Dr Inventory (or Expense) / Cr GRN Accrual` posting — populated at
 * creation time in the same transaction as the row itself, never
 * patched in afterward (the same discipline `FIN-09`'s
 * `stock_movements` uses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_notes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('grn_number', 40);
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('delivery_note_ref', 60)->nullable();
            $table->date('received_on');
            $table->foreignId('received_by')->constrained('users');
            $table->foreignId('inspected_by')->nullable()->constrained('users');
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->boolean('is_partial')->default(false);
            $table->boolean('has_rejections')->default(false);
            $table->bigInteger('total_value_minor');
            $table->string('currency', 3);
            $table->string('status', 20);
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->json('photo_file_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'grn_number']);
            $table->index(['school_id', 'purchase_order_id'], 'grn_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_notes');
    }
};
