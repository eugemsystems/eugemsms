<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-02 §6 (`Ops\Maintenance\Contractors`, `maintenance.manage`). Nothing anywhere in
 * `suppliers` classified a supplier as a contractor — `supplier_type` is a legal-entity-type field
 * (company|sole_trader|individual|government|ngo, per Book H1 FIN-08's own spec), not a role, and a
 * work order's own `assigned_team`/`contractor_supplier_id` columns record which specific supplier
 * did a given job, never which suppliers are generally available to be assigned one. This flag is
 * the missing piece between the two: `Ops\Maintenance\Contractors` lists suppliers flagged here,
 * each with their work-order cost/SLA history already queryable via `work_orders.contractor_supplier_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('is_contractor')->default(false)->after('supplier_type');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('is_contractor');
        });
    }
};
