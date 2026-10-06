<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 BR-FIN-10-002 — the deliberate column choice the FIN-10 pass
 * deferred: a capital PO line (FIN-08) and a capitalisable item (FIN-09) each
 * name the asset category they become, so capitalisation can run automatically.
 * Null keeps the earlier behaviour (a human capitalises manually).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_category_id');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_category_id');
        });
    }
};
