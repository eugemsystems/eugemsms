<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-001 (AC-COM-08-001). `respondent_type`/
 * `respondent_id` are NULL, unconditionally, for an anonymous survey —
 * absence, never redaction (mirrors BRD-08's own anonymous reporting
 * principle, cited directly by BR-COM-08-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('respondent_type', 20)->nullable();
            $table->unsignedBigInteger('respondent_id')->nullable();
            $table->timestamp('submitted_at');

            $table->index(['school_id', 'survey_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
