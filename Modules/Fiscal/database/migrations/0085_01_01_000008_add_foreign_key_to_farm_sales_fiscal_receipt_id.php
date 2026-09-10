<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3, closing the gap `Modules\Farm`'s own
 * `farm_sales` migration docblock names explicitly: `fiscal_receipt_id`
 * was left a plain, unconstrained `unsignedBigInteger` because
 * `fiscal_receipts` didn't exist. It does now — this adds the real FK
 * constraint onto that already-existing column; no new column, no
 * change to any row currently in it (all null, since nothing has
 * populated it yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_sales', function (Blueprint $table): void {
            $table->foreign('fiscal_receipt_id')->references('id')->on('fiscal_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farm_sales', function (Blueprint $table): void {
            $table->dropForeign(['fiscal_receipt_id']);
        });
    }
};
