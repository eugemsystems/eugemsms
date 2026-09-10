<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-001/002/017. `net_book_value_minor` is a
 * cache kept in step by every posting action, verified against the
 * ledger nightly (BR-FIN-10-019) — never the sole source of truth.
 * `currency`/`base_cost_minor` exist for record-keeping only
 * (BR-FIN-10-017 ⭐ — an asset is never revalued for FX; it stays at
 * historical cost in base currency for the rest of its life).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('asset_tag', 40);
            $table->foreignId('category_id')->constrained('asset_categories');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->date('acquisition_date');
            $table->bigInteger('acquisition_cost_minor');
            $table->string('currency', 3);
            $table->bigInteger('base_cost_minor');
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            $table->string('acquisition_source', 30);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->foreignId('grn_id')->nullable()->constrained('goods_received_notes');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements');
            $table->string('donor_name', 200)->nullable();
            $table->boolean('is_depreciable')->default(true);
            $table->string('depreciation_method', 30);
            $table->decimal('useful_life_years', 4, 1)->nullable();
            $table->bigInteger('residual_value_minor')->default(0);
            $table->date('depreciation_start_date')->nullable();
            $table->decimal('total_units_expected', 14, 2)->nullable();
            $table->decimal('units_consumed', 14, 2)->default(0);
            $table->bigInteger('accumulated_depreciation_minor')->default(0);
            $table->bigInteger('net_book_value_minor');
            $table->date('last_depreciated_on')->nullable();
            $table->boolean('fully_depreciated')->default(false);
            $table->string('location', 150)->nullable();
            $table->string('building', 80)->nullable();
            $table->string('room', 60)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('cost_centre_id')->constrained('cost_centres');
            $table->foreignId('custodian_staff_id')->nullable()->constrained('staff');
            $table->string('status', 20);
            $table->string('condition', 20)->default('good');
            $table->date('warranty_expires_on')->nullable();
            $table->json('photo_file_ids')->nullable();
            $table->string('barcode', 60)->nullable();
            $table->string('qr_code', 80)->nullable();
            $table->date('last_verified_on')->nullable();
            $table->date('next_verification_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'asset_tag']);
            $table->index(['school_id', 'category_id', 'status'], 'assets_category_status_idx');
            $table->index(['school_id', 'cost_centre_id'], 'assets_cost_centre_idx');
            $table->index(['school_id', 'next_verification_on'], 'assets_next_verification_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
