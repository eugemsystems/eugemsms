<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2/§3/§4 ⭐ — the chain of custody of a child.
 * `collecting_person_id_no` is ENCRYPTED at rest — enforced by the
 * model's own cast (`encrypted` cast on `Exeat::collecting_person_id_no`),
 * not a DB-level feature this migration configures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exeats', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained();
            $table->string('exeat_number', 40);
            $table->foreignId('student_id')->constrained();
            $table->foreignId('exeat_type_id')->constrained('exeat_types');
            $table->foreignId('requested_by_guardian_id')->nullable()->constrained('guardians');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users');
            $table->string('request_source', 20);
            $table->text('reason');
            $table->unsignedBigInteger('supporting_document_id')->nullable();
            $table->timestamp('departs_at');
            $table->timestamp('returns_by');
            $table->string('destination_address', 255);
            $table->string('destination_city', 100)->nullable();
            $table->string('destination_province', 60)->nullable();
            $table->char('destination_country', 2)->default('ZW');
            $table->string('contact_phone', 30);
            $table->foreignId('collecting_guardian_id')->nullable()->constrained('guardians');
            $table->string('collecting_person_name', 150)->nullable();
            $table->text('collecting_person_id_no')->nullable();
            $table->string('collecting_person_phone', 30)->nullable();
            $table->string('collection_method', 30);
            $table->foreignId('one_off_authorisation_by')->nullable()->constrained('guardians');
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->unsignedBigInteger('pass_document_id')->nullable();
            $table->string('verification_code', 40)->nullable()->unique();
            $table->timestamp('actual_departure_at')->nullable();
            $table->foreignId('departure_recorded_by')->nullable()->constrained('users');
            $table->string('departure_verified_by', 150)->nullable();
            $table->timestamp('actual_return_at')->nullable();
            $table->foreignId('return_recorded_by')->nullable()->constrained('users');
            $table->timestamp('overdue_notified_at')->nullable();
            $table->smallInteger('late_return_minutes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'exeat_number'], 'exeats_number_unique');
            $table->index(['school_id', 'student_id', 'term_id'], 'exeats_student_idx');
            $table->index(['school_id', 'status', 'departs_at'], 'exeats_departs_idx');
            $table->index(['school_id', 'status', 'returns_by'], 'exeats_returns_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exeats');
    }
};
