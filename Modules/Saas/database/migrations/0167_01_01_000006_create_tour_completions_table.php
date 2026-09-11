<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2/BR-SAA-03-005 — both completion and skip recorded;
 * a skipped tour stays reachable from help (mirrors `COM-03`'s
 * onboarding-step pattern).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key', 60);
            $table->foreign('tour_key')->references('key')->on('product_tours')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_completions');
    }
};
