<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-11 §2/BR-ACA-11-004. `criteria` uses the same
 * criterion/descriptor-levels JSON shape as `ACA-06`'s project
 * rubrics — a conceptual reuse, not a literal shared table (the spec
 * lists this as its own table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_rubrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('criteria');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_rubrics');
    }
};
