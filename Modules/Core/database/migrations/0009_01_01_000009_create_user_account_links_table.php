<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2. Parent↔learner, staff↔teacher identity link —
 * `ACT-LinkAccount`. `linked_type`/`linked_id` are a lightweight morph
 * pair (not `morphs()`) because the linked records (`students`,
 * `guardians`, `staff`) belong to modules that don't exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_account_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('linked_type', 60);
            $table->unsignedBigInteger('linked_id');
            $table->boolean('is_active')->default(true);

            $table->unique(['user_id', 'linked_type', 'linked_id']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_links');
    }
};
