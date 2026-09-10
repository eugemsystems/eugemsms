<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2. `checkpoint_id` is a real FK into `BRD-02`'s own
 * `movement_checkpoints`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patrol_id')->constrained('patrols');
            $table->foreignId('checkpoint_id')->constrained('movement_checkpoints');
            $table->timestamp('scanned_at');
            $table->string('method', 20);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();

            $table->index(['patrol_id'], 'patrol_scans_patrol_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_scans');
    }
};
