<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-07 §2/BR-ACA-07-010/011. `duty_assignment_id` links back
 * to `PPL-04`'s `duty_assignments` — exam duty counts toward that
 * module's own fairness balancing, reusing the roster rather than
 * building a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invigilation_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->constrained('examination_papers');
            $table->foreignId('venue_id')->constrained();
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('role', 20);
            $table->foreignId('duty_assignment_id')->nullable()->constrained('duty_assignments');
            $table->boolean('confirmed')->default(false);
            $table->boolean('attended')->nullable();
            $table->boolean('report_submitted')->default(false);
            $table->text('report_notes')->nullable();

            $table->unique(['paper_id', 'venue_id', 'staff_id'], 'invigilation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invigilation_assignments');
    }
};
