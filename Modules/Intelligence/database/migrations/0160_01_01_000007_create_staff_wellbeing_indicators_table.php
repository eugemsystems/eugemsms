<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2/BR-INT-03-009/010. `workload_utilisation_percent`
 * is copied straight from `Modules\People\Models\StaffWorkload`'s own
 * `utilisation_percent` (PPL-04) — never recomputed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_wellbeing_indicators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->decimal('workload_utilisation_percent', 5, 2)->nullable();
            $table->tinyInteger('consecutive_terms_over_ceiling')->default(0);
            $table->string('sick_leave_days_trend', 20)->nullable();
            $table->string('flag_level', 20);
            $table->timestamps();

            $table->unique(['school_id', 'staff_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_wellbeing_indicators');
    }
};
