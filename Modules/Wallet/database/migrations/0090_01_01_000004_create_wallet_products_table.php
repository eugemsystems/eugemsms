<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-14 §2. `item_id` (`FIN-09` `inventory_items`) is a real
 * FK, nullable — not every wallet product depletes tracked stock.
 * `category` is the parent-blocking key `student_wallets.
 * blocked_categories` matches against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spend_point_id')->constrained('spend_points');
            $table->foreignId('item_id')->nullable()->constrained('inventory_items');
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('category', 40);
            $table->bigInteger('price_minor');
            $table->char('currency', 3);
            $table->string('tax_type', 20);
            $table->string('barcode', 60)->nullable();
            $table->unsignedBigInteger('image_file_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->nullable();

            $table->unique(['school_id', 'spend_point_id', 'code'], 'wallet_products_school_point_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_products');
    }
};
