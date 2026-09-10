<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-14 §2. `stock_movement_id` (`FIN-09`) is a real FK —
 * set only when the product's own `item_id` is not null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('wallet_sales');
            $table->foreignId('product_id')->constrained('wallet_products');
            $table->decimal('quantity', 10, 2);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->string('tax_type', 20);
            $table->bigInteger('tax_minor')->default(0);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_sale_lines');
    }
};
