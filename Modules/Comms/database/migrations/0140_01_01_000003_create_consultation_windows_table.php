<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-07 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_windows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('event_name', 150);
            $table->smallInteger('slot_duration_minutes')->default(10);
            $table->timestamp('available_from');
            $table->timestamp('available_to');
            $table->timestamp('booking_opens_at')->nullable();
            $table->timestamp('booking_closes_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_windows');
    }
};
