<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-016. `receipt_id` is a real FK into
 * `FIN-04`'s `receipts`. `fiscal_receipt_id` is a plain forward
 * reference — `FIN-13` (Book H3, fiscalisation/compliance) doesn't
 * exist yet in this codebase, the same "not built yet" boundary this
 * book's own OPS-01/OPS-02 used for each other before both existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_sales', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->string('sale_number', 40);
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->date('sale_date');
            $table->string('buyer_name', 200);
            $table->string('buyer_contact', 80)->nullable();
            $table->string('item_description', 255);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('total_minor');
            $table->char('currency', 3);
            $table->bigInteger('cost_of_sales_minor')->nullable();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts');
            $table->unsignedBigInteger('fiscal_receipt_id')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');

            $table->unique(['school_id', 'sale_number'], 'farm_sales_school_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_sales');
    }
};
