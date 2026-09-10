<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 FIN-13 §3, closing the gap `Modules\Finance`'s own
 * `receipts` migration docblock names explicitly: "`fiscal_receipt_id`
 * (FK to a `fiscal_receipts` table `FIN-13` owns) is omitted — that
 * table doesn't exist [yet]". It does now — this additive, nullable
 * column is the real link `RouteReceiptForFiscalisationListener`
 * populates; `fiscalisation_status` (already on this table) is
 * unaffected and stays the quick-glance summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->foreignId('fiscal_receipt_id')->nullable()->after('fiscalisation_status')->constrained('fiscal_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fiscal_receipt_id');
        });
    }
};
