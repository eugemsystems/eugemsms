<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-016/017.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_id')->constrained('duty_rosters')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20);
            $table->foreignId('swapped_with_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('swap_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->index(['school_id', 'staff_id', 'starts_at']);
            $table->index(['school_id', 'roster_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_assignments');
    }
};
