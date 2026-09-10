<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-004/005.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrols', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patrol_route_id')->constrained('patrol_routes');
            $table->foreignId('guard_staff_id')->constrained('staff');
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->smallInteger('checkpoints_expected');
            $table->smallInteger('checkpoints_scanned')->default(0);
            $table->string('status', 20);
            $table->text('findings')->nullable();

            $table->index(['school_id', 'scheduled_at', 'status'], 'patrols_scheduled_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrols');
    }
};
