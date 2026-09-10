<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-009 ⭐ — APPEND-ONLY, the same
 * model-level-guard-not-DB-grant discipline as `FIN-09`'s
 * `stock_movements` (see that migration's own docblock for why).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('fixed_assets');
            $table->string('movement_type', 30);
            $table->string('from_value', 255)->nullable();
            $table->string('to_value', 255)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('occurred_at');

            $table->index(['asset_id'], 'asset_movements_asset_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
