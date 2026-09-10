<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-01 §2 ⭐. BR-ACA-01-004: `subject_group_id` is the join
 * point `FIN-02` prices per-subject rates against — a subject with no
 * group falls back to the structure item's base rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_groups', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('requires_laboratory')->default(false);
            $table->boolean('requires_workshop')->default(false);
            $table->smallInteger('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_groups');
    }
};
