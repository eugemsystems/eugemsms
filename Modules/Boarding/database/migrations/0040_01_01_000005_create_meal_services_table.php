<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-04 §2/§3 ⭐ — one meal, one day, actually served.
 * `present_boarders` is the number that drives servings, read live
 * from `BRD-02`'s `LiveOccupancyProvider`, never from allocated beds.
 * `requisition_id` is a forward reference to `FIN-09` (not built).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_services', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->date('service_date');
            $table->string('meal', 20);
            $table->foreignId('menu_day_id')->nullable()->constrained('menu_days');
            $table->smallInteger('nominal_boarders')->default(0);
            $table->smallInteger('present_boarders')->default(0);
            $table->smallInteger('on_exeat')->default(0);
            $table->smallInteger('in_sick_bay')->default(0);
            $table->smallInteger('staff_meals')->default(0);
            $table->smallInteger('guest_meals')->default(0);
            $table->smallInteger('planned_servings')->default(0);
            $table->smallInteger('actual_served')->nullable();
            $table->bigInteger('issued_cost_minor')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('cost_per_serving_minor')->nullable();
            $table->string('wastage_note', 255)->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('requisition_id')->nullable();
            $table->foreignId('prepared_by_staff_id')->nullable()->constrained('staff');

            $table->unique(['school_id', 'service_date', 'meal'], 'meal_services_unique');
            $table->index(['school_id', 'term_id', 'service_date'], 'meal_services_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_services');
    }
};
