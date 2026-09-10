<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_competitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->string('name', 150);
            $table->string('competition_type', 30);
            $table->date('held_on')->nullable();
            $table->json('points_scheme');
            $table->decimal('weight', 5, 2)->default(1);
            $table->string('status', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_competitions');
    }
};
