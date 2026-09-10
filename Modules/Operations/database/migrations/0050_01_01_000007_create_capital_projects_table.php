<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-015. `capitalise_on_completion` is the
 * real boundary flag into `FIN-10` — unlike `FIN-08`/`FIN-09`'s own
 * capitalisation flags (deferred there for lacking an
 * `asset_category_id` mapping), this table is new in this same build
 * and carries `asset_category_id` itself, so `FIN-10`'s
 * `CapitalizeAssetAction` is wired for real on completion when both
 * it and `capitalise_on_completion` are set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_projects', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('project_number', 40);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->bigInteger('budget_minor');
            $table->string('currency', 3);
            $table->bigInteger('committed_minor')->default(0);
            $table->bigInteger('spent_minor')->default(0);
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines');
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories');
            $table->date('starts_on');
            $table->date('target_completion')->nullable();
            $table->date('actual_completion')->nullable();
            $table->foreignId('project_manager_id')->nullable()->constrained('staff');
            $table->foreignId('main_contractor_id')->nullable()->constrained('suppliers');
            $table->string('status', 20);
            $table->boolean('capitalise_on_completion')->default(true);

            $table->unique(['school_id', 'project_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_projects');
    }
};
