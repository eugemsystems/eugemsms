<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/BR-BRD-02-001. `hostel_id` null means the point
 * applies school-wide; a hostel row overrides it for that hostel only
 * (a junior and a sixth-form hostel legitimately run different
 * schedules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_call_points', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->nullable()->constrained('hostels');
            $table->string('code', 30);
            $table->string('name', 80);
            $table->time('scheduled_time');
            $table->json('applies_on_days');
            $table->boolean('applies_in_term_only')->default(true);
            $table->smallInteger('grace_minutes')->default(10);
            $table->boolean('is_mandatory')->default(true);
            $table->foreignId('escalation_profile_id')->nullable()->constrained('escalation_profiles');
            $table->smallInteger('sort_order')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'hostel_id', 'code'], 'roll_call_points_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_call_points');
    }
};
