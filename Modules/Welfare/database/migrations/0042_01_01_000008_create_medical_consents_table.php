<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-06 §2/BR-BRD-06-011/012 ⭐ — no medication without a
 * valid, unwithdrawn consent from a guardian holding `may_authorise_medical`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_consents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('guardian_id')->constrained();
            $table->string('consent_type', 40);
            $table->string('scope_detail', 255)->nullable();
            $table->boolean('granted');
            $table->timestamp('granted_at');
            $table->string('granted_via', 30);
            $table->foreignId('witness_staff_id')->nullable()->constrained('staff');
            $table->unsignedBigInteger('document_file_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdrawn_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'consent_type', 'granted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_consents');
    }
};
