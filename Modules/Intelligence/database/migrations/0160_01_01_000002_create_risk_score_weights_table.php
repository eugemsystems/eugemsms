<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-03 §2/BR-INT-03-003. A school may re-weight or disable
 * any registered indicator for itself; it cannot invent one that
 * isn't in `risk_indicators` first — enforced by the FK, not by
 * convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_score_weights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_key', 60);
            $table->decimal('weight', 5, 2);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('indicator_key')->references('key')->on('risk_indicators');
            $table->unique(['school_id', 'indicator_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_score_weights');
    }
};
