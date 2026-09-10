<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2/BR-BRD-03-022.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visiting_day_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visiting_day_id')->constrained('visiting_days');
            $table->foreignId('student_id')->constrained();
            $table->foreignId('guardian_id')->constrained('guardians');
            $table->time('slot_starts_at');
            $table->tinyInteger('party_size')->default(1);
            $table->string('status', 20);
            $table->timestamp('booked_at');

            $table->unique(['visiting_day_id', 'student_id', 'slot_starts_at'], 'visiting_day_bookings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visiting_day_bookings');
    }
};
