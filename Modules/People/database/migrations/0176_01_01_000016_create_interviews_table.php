<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-02 §2. An applicant interview with panel scores and a recommendation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('venue', 150)->nullable();
            $table->json('panel_user_ids');
            $table->boolean('attended')->nullable();
            $table->json('scores')->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->string('recommendation', 20)->nullable();
            $table->text('panel_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['school_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
