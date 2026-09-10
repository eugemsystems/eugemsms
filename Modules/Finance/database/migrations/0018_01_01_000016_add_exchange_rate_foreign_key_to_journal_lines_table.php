<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §2's `journal_lines.exchange_rate_id` had no FK
 * constraint when that migration ran — `exchange_rates` is FIN-06,
 * built directly after. Added now that the target table exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->foreign('exchange_rate_id')->references('id')->on('exchange_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropForeign(['exchange_rate_id']);
        });
    }
};
