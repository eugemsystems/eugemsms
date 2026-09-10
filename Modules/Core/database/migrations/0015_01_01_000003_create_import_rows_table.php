<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-11 §2/BR-CORE-11-006. `created_type`/`created_id`
 * enable precise, per-row rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->integer('row_number');
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('errors')->nullable();
            $table->string('created_type')->nullable();
            $table->unsignedBigInteger('created_id')->nullable();

            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
