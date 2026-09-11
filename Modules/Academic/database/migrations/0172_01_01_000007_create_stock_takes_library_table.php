<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-10 §2/BR-ACA-10-009. `scanned_copy_ids` and
 * `confirmatory_pass_done` are this migration's own additions — the
 * spec's literal table only keeps aggregate counts, but reconciling
 * "which copies were actually scanned" and gating the lost-marking
 * step behind an explicit second pass needs somewhere to record both
 * (deviation noted per this project's convention of documenting spec
 * extensions inline). Table name is `stock_takes_library` (word order
 * per spec) — the `LibraryStockTake` model sets `protected $table`
 * explicitly since Eloquent's guess would be `library_stock_takes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes_library', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('conducted_on');
            $table->integer('expected_count');
            $table->integer('scanned_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->json('scanned_copy_ids')->nullable();
            $table->boolean('confirmatory_pass_done')->default(false);
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes_library');
    }
};
