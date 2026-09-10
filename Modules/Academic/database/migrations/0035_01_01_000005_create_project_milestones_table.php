<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brief_id')->constrained('project_briefs')->cascadeOnDelete();
            $table->tinyInteger('sequence');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('due_on');
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->boolean('requires_evidence')->default(true);

            $table->unique(['brief_id', 'sequence'], 'project_milestones_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
