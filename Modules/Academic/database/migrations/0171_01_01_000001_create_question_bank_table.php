<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-09 §2. Table is `question_bank` (singular, per spec) —
 * the `QuestionBankItem` model sets `protected $table` explicitly
 * since Eloquent's default guess would be `question_banks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->string('topic', 150)->nullable();
            $table->string('syllabus_objective_ref', 80)->nullable();
            $table->string('item_type', 20);
            $table->string('difficulty', 20);
            $table->text('prompt');
            $table->foreignId('prompt_image_file_id')->nullable()->constrained('files');
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable();
            $table->decimal('max_mark', 6, 2);
            $table->boolean('is_auto_markable');
            $table->decimal('difficulty_index', 5, 2)->nullable();
            $table->decimal('discrimination_index', 5, 2)->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'subject_id', 'topic', 'difficulty'], 'question_bank_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank');
    }
};
