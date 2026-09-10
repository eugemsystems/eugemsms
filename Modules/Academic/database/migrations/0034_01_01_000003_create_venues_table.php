<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §2 — rooms, labs, workshops, fields.
 * `is_bookable_externally` is `OPS-05`'s own concern, not built here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('venue_type', 30);
            $table->string('building', 80)->nullable();
            $table->string('floor', 20)->nullable();
            $table->smallInteger('capacity');
            $table->smallInteger('exam_capacity')->nullable();
            $table->json('facilities')->nullable();
            $table->boolean('is_bookable_externally')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'venues_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
