<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-01 §2/BR-INT-01-007. One row per entity per school per
 * night, tracking a rebuild — not a copy of the denormalised rows
 * themselves (those live in whatever table `RebuildWarehouseSnapshotAction`
 * actually writes into, out of this pass's real starting scope; see
 * that action's own docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('entity_key', 60);
            $table->date('snapshot_date');
            $table->integer('row_count');
            $table->timestamp('rebuilt_at');
            $table->integer('duration_ms')->nullable();

            $table->unique(['school_id', 'entity_key', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_snapshots');
    }
};
