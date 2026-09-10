<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2/§3 ⭐/BR-OPS-01-013/014. `store_requisition_id` is
 * a real FK into `FIN-09`'s `store_requisitions` for a school-tank
 * draw, and `journal_id` the real journal `IssueStockAction` posts for
 * it — the same "real linkage, not a shadow figure" pattern
 * `OPS-02`'s own `IssuePartsToWorkOrderAction` uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->timestamp('fuelled_at');
            $table->decimal('odometer_km', 12, 2);
            $table->decimal('litres', 8, 2);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('total_cost_minor');
            $table->char('currency', 3);
            $table->string('source', 20);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->foreignId('store_requisition_id')->nullable()->constrained('store_requisitions');
            $table->unsignedBigInteger('receipt_file_id')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('drivers');
            $table->foreignId('authorised_by')->nullable()->constrained('users');
            $table->decimal('km_since_last', 10, 2)->nullable();
            $table->decimal('km_per_litre', 6, 2)->nullable();
            $table->decimal('variance_percent', 6, 2)->nullable();
            $table->boolean('is_anomaly')->default(false);
            $table->foreignId('anomaly_reviewed_by')->nullable()->constrained('users');
            $table->text('anomaly_explanation')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');

            $table->index(['school_id', 'vehicle_id', 'fuelled_at'], 'fuel_logs_vehicle_fuelled_idx');
            $table->index(['school_id', 'is_anomaly'], 'fuel_logs_anomaly_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
