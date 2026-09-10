<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-015 — milk, eggs, meat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_outputs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->date('output_date');
            $table->string('output_type', 30);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20);
            $table->bigInteger('unit_cost_minor')->nullable();
            $table->char('currency', 3);
            $table->string('destination', 20);
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots');
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('recorded_by')->constrained('users');

            $table->unique(['production_unit_id', 'output_date', 'output_type'], 'production_outputs_unit_date_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_outputs');
    }
};
