<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2 — year groups for reunions. An alumnus belongs to
 * the group matching their own `graduation_year`, not a stored FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_house_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('graduation_year');
            $table->string('group_name', 120)->nullable();
            $table->foreignId('coordinator_alumnus_id')->nullable()->constrained('alumni');
            $table->timestamps();

            $table->unique(['school_id', 'graduation_year'], 'alumni_house_groups_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_house_groups');
    }
};
