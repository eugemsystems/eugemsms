<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-01 §2 ⭐/BR-OPS-01-006/007/008. `fee_line_id` is a real
 * FK into `FIN-02`'s `learner_fee_lines` — populated only once the
 * `usage_based` billing basis this module drives actually exists
 * there (see `transport_zones`' own migration docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_transport', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('route_id')->constrained('routes');
            $table->foreignId('pickup_stop_id')->constrained('route_stops');
            $table->foreignId('dropoff_stop_id')->nullable()->constrained('route_stops');
            $table->foreignId('zone_id')->constrained('transport_zones');
            $table->string('direction', 20);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20);
            $table->string('billing_status', 20)->default('pending');
            $table->foreignId('fee_line_id')->nullable()->constrained('learner_fee_lines');
            $table->boolean('authorised_by_guardian')->default(false);
            $table->string('notes', 255)->nullable();

            $table->unique(['school_id', 'term_id', 'student_id', 'direction', 'effective_from'], 'learner_transport_unique');
            $table->index(['school_id', 'route_id', 'status'], 'learner_transport_route_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_transport');
    }
};
