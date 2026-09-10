<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-002. `skip_logic` — `{if_answer,
 * go_to_sequence}` — is evaluated by `Modules\Comms\Domain\Support\SkipLogicEvaluator`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('sequence');
            $table->string('question_type', 20);
            $table->string('prompt', 500);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('skip_logic')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
