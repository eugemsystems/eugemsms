<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-04 §2/BR-ACA-04-014. Cache, recomputed per staff/day —
 * "unmarked registers appear on the deputy head's dashboard the same
 * day, not at term end".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_marking_compliance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('staff_id')->constrained();
            $table->date('session_date');
            $table->smallInteger('expected_sessions')->default(0);
            $table->smallInteger('marked_sessions')->default(0);
            $table->smallInteger('marked_late_sessions')->default(0);
            $table->decimal('compliance_percent', 5, 2)->nullable();

            $table->unique(['school_id', 'staff_id', 'session_date'], 'attendance_marking_compliance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_marking_compliance');
    }
};
