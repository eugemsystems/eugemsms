<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-02 §2/BR-INT-02-002. `school_id` nullable — null is a
 * system default KPI (this pass's own registered starting set,
 * materialized the same way `dashboard_widgets` is), a school's own
 * value is a school-specific KPI it defined itself. No `BelongsToSchool`
 * for the same reason `provider_rate_cards` (COM-01) doesn't use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 60)->unique();
            $table->string('module_code', 20);
            $table->string('label', 150);
            $table->string('unit', 20);
            $table->string('data_source_endpoint', 200);
            $table->boolean('higher_is_better')->default(true);
            $table->decimal('default_target_value', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};
