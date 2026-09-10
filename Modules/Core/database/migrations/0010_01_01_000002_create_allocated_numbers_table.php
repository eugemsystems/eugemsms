<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-06 §2/§3. Append-only in spirit — a row's `sequence` and
 * `formatted_number` never change after insert; only `status`/`used_at`/
 * `voided_at`/`void_reason` are ever updated (BR-CORE-06-002).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocated_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('numbering_series')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('formatted_number', 80);
            $table->string('document_type', 40);
            $table->string('documentable_type')->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->string('status', 20)->default('allocated');
            $table->text('void_reason')->nullable();
            $table->foreignId('allocated_by')->constrained('users');
            $table->timestamp('allocated_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->unique(['series_id', 'sequence']);
            $table->unique(['school_id', 'formatted_number']);
            $table->index(['documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocated_numbers');
    }
};
