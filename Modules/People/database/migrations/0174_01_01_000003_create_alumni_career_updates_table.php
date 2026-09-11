<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/BR-PPL-06-004. Self-reported; `verified = 0` until
 * the school confirms it, but still displayed either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_career_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alumnus_id')->constrained('alumni')->cascadeOnDelete();
            $table->string('update_type', 20);
            $table->string('title', 200);
            $table->string('institution_or_employer', 200)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('verified')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['school_id', 'alumnus_id'], 'alumni_career_updates_alumnus_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_career_updates');
    }
};
