<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3. A learner or guardian belonging to a household, with dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('member_type', 20);
            $table->unsignedBigInteger('member_id');
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->unique(['household_id', 'member_type', 'member_id'], 'household_members_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
    }
};
