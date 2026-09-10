<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2. `checkpoint_ids` references `BRD-02`'s own
 * `movement_checkpoints` ids — a JSON array, not a pivot table, since
 * order matters for a patrol route and the spec's own schema stores
 * it this way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->json('checkpoint_ids');
            $table->smallInteger('expected_duration_min')->nullable();
            $table->string('frequency', 30);
            $table->json('applies_at_times')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'patrol_routes_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_routes');
    }
};
