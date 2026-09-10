<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/§3/BR-FIN-09-013. `preferred_supplier_id` is a
 * forward reference to `FIN-08` (built after this module) — plain
 * nullable column, no FK, matching this codebase's own established
 * forward-reference pattern. `image_file_id` likewise has no FK
 * (`CORE-10` file rows are referenced this way throughout).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('item_categories');
            $table->string('base_unit', 20);
            $table->string('purchase_unit', 20)->nullable();
            $table->decimal('purchase_conversion', 12, 6)->default(1);
            $table->string('issue_unit', 20)->nullable();
            $table->decimal('issue_conversion', 12, 6)->default(1);
            $table->boolean('is_perishable')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->smallInteger('shelf_life_days')->nullable();
            $table->boolean('is_high_risk')->default(false);
            $table->boolean('is_saleable')->default(false);
            $table->bigInteger('sale_price_minor')->nullable();
            $table->string('sale_currency', 3)->nullable();
            $table->foreignId('sale_fee_component_id')->nullable()->constrained('fee_components');
            $table->boolean('is_capitalisable')->default(false);
            $table->bigInteger('capitalisation_threshold_minor')->nullable();
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts');
            $table->unsignedBigInteger('preferred_supplier_id')->nullable();
            $table->bigInteger('standard_cost_minor')->nullable();
            $table->string('standard_cost_currency', 3)->nullable();
            $table->string('barcode', 60)->nullable();
            $table->unsignedBigInteger('image_file_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'category_id', 'is_active'], 'inventory_items_category_idx');
            $table->index(['school_id', 'is_high_risk'], 'inventory_items_high_risk_idx');

            // sqlite (the test suite's driver) has no fulltext index
            // support at all, so this only runs on MySQL/PostgreSQL.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
