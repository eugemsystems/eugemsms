<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations');
            $table->foreignId('requisition_line_id')->nullable()->constrained('purchase_requisition_lines');
            $table->string('description', 500);
            $table->decimal('quantity', 14, 4);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->smallInteger('lead_time_days')->nullable();

            $table->index(['quotation_id'], 'quotation_lines_quotation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
    }
};
