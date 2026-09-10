<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_response_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('survey_questions');
            $table->json('answer_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_response_answers');
    }
};
