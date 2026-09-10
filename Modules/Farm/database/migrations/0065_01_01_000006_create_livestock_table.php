<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-03 §2/BR-OPS-03-010/011. `fixed_asset_id` is a real FK
 * into `FIN-10` — breeding stock above the capitalisation threshold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livestock', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_unit_id')->constrained('production_units');
            $table->string('tag_number', 40)->nullable();
            $table->string('species', 40);
            $table->string('breed', 80)->nullable();
            $table->boolean('is_herd_record')->default(false);
            $table->integer('head_count')->default(1);
            $table->string('sex', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('acquired_on')->nullable();
            $table->string('acquisition_type', 20)->nullable();
            $table->bigInteger('acquisition_cost_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('purpose', 30);
            $table->string('status', 20);
            $table->date('disposal_on')->nullable();
            $table->string('disposal_reason', 255)->nullable();
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets');

            $table->index(['school_id', 'production_unit_id', 'status'], 'livestock_unit_status_idx');
            $table->index(['school_id', 'species', 'status'], 'livestock_species_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livestock');
    }
};
