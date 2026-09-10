<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2. Named `capital_project_milestones`, not the
 * spec's own literal `project_milestones` — `Modules\Academic` (Book
 * E, `ACA-0X` project-based learning) already owns a `project_milestones`
 * table for an entirely different concept. Renamed here to the
 * unambiguous, parent-prefixed form rather than colliding with an
 * already-shipped table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('capital_projects');
            $table->smallInteger('sequence');
            $table->string('name', 150);
            $table->date('target_date');
            $table->date('completed_date')->nullable();
            $table->decimal('payment_percent', 5, 2)->nullable();
            $table->string('status', 20);

            $table->index(['project_id'], 'capital_project_milestones_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_project_milestones');
    }
};
