<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/§3 ⭐ — the allocation engine's configurable rule
 * set (soft constraints only; the hard constraints in §3 — gender,
 * bed availability, room in service, incompatibility, medical
 * proximity, mobility — are never rows here, they are unconditional
 * code in `BedAllocationEngine`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_constraints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('constraint_type', 40);
            $table->string('severity', 10);
            $table->smallInteger('weight')->default(1);
            $table->foreignId('hostel_id')->nullable()->constrained('hostels');
            $table->json('grade_level_ids')->nullable();
            $table->smallInteger('value')->nullable();
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_constraints');
    }
};
