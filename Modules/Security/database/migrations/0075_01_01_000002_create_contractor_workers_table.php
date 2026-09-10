<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2 ⭐/BR-OPS-06-002. `id_number` is stored `TEXT` and
 * cast `encrypted` on the model, matching every other ENCRYPTED field
 * in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_workers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('contractors');
            $table->string('full_name', 150);
            $table->text('id_number')->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->date('induction_completed_on')->nullable();
            $table->date('police_clearance_on')->nullable();
            $table->boolean('is_cleared')->default(false);
            $table->string('badge_number', 30)->nullable();

            $table->index(['school_id', 'contractor_id'], 'contractor_workers_contractor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_workers');
    }
};
