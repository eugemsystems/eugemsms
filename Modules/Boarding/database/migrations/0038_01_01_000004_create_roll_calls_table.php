<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/BR-BRD-02-002/007 ⭐. `expected_count` is derived
 * from active `bed_allocations` on `roll_date` at open time, never a
 * stored list re-read later. `roll_date` is set by the server, never
 * trusted from a device clock (BR-BRD-02-007).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_calls', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('roll_call_point_id')->constrained('roll_call_points');
            $table->foreignId('hostel_id')->constrained('hostels');
            $table->date('roll_date');
            $table->timestamp('scheduled_at');
            $table->smallInteger('expected_count')->default(0);
            $table->smallInteger('present_count')->default(0);
            $table->smallInteger('accounted_count')->default(0);
            $table->smallInteger('missing_count')->default(0);
            $table->string('status', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('conducted_by')->nullable()->constrained('users');
            $table->string('device_source', 20)->nullable();

            $table->unique(['school_id', 'roll_call_point_id', 'hostel_id', 'roll_date'], 'roll_calls_unique');
            $table->index(['school_id', 'roll_date', 'status'], 'roll_calls_date_idx');
            $table->index(['school_id', 'status', 'missing_count'], 'roll_calls_missing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_calls');
    }
};
