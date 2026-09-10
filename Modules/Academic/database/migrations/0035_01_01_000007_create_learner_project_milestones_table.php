<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_project_id')->constrained('learner_projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('project_milestones');
            $table->string('status', 20);
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('mark', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');

            $table->unique(['learner_project_id', 'milestone_id'], 'learner_project_milestones_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_project_milestones');
    }
};
