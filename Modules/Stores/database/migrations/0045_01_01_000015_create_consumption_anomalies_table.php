<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/BR-FIN-09-023.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('expected_quantity', 14, 4);
            $table->decimal('actual_quantity', 14, 4);
            $table->decimal('variance_percent', 6, 2);
            $table->decimal('occupancy_factor', 6, 2)->nullable();
            $table->string('severity', 20);
            $table->string('status', 20);
            $table->text('investigation_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('detected_at');

            $table->index(['school_id', 'status', 'severity'], 'consumption_anomalies_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_anomalies');
    }
};
