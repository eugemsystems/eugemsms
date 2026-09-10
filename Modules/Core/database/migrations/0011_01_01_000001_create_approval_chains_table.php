<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-07 §2/BR-CORE-07-001. Chain selection evaluates active
 * chains for the type in `priority` order; the first whose
 * `condition_rules` match applies, falling back to the default chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_chains', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type', 60);
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->json('condition_rules')->nullable();
            $table->boolean('is_default')->default(false);
            $table->smallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'approvable_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chains');
    }
};
