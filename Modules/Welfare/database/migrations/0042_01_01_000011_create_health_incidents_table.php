<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-020/021. `referral_id` intentionally has
 * no FK constraint — `external_referrals` links back here via
 * `incident_id`, and constraining both directions would create a
 * migration-order cycle neither table can resolve on its own; the
 * same forward-reference-without-constraint shape already used for
 * `ad_hoc_charge_id` elsewhere in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_incidents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->string('incident_type', 40);
            $table->timestamp('occurred_at');
            $table->string('location', 150);
            $table->string('activity_at_time', 150)->nullable();
            $table->text('description');
            $table->json('witnesses')->nullable();
            $table->text('first_aid_given')->nullable();
            $table->foreignId('first_aider_staff_id')->nullable()->constrained('staff');
            $table->foreignId('admission_id')->nullable()->constrained('sick_bay_admissions');
            $table->unsignedBigInteger('referral_id')->nullable();
            $table->timestamp('guardian_notified_at')->nullable();
            $table->string('severity', 20);
            $table->boolean('is_reportable')->default(false);
            $table->string('reported_to', 150)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->json('photo_file_ids')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'term_id', 'occurred_at']);
            $table->index(['school_id', 'severity', 'is_reportable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_incidents');
    }
};
