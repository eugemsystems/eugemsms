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
        Schema::create('visiting_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->date('visit_date');
            $table->string('name', 120);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->smallInteger('slot_duration_minutes')->nullable();
            $table->smallInteger('max_per_slot')->nullable();
            $table->json('applies_to_hostels')->nullable();
            $table->timestamp('booking_opens_at')->nullable();
            $table->timestamp('booking_closes_at')->nullable();
            $table->string('status', 20);

            $table->unique(['school_id', 'visit_date'], 'visiting_days_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visiting_days');
    }
};
