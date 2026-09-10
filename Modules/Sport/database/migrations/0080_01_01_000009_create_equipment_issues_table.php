<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §3/BR-OPS-07-011 — "equipment issued for an activity
 * is tracked through FIN-09 and must be returned at season end". A
 * genuine gap: `Modules\Stores`'s Book H1 asset register (`FIN-09`)
 * tracks a `FixedAsset`'s custodian only as `custodian_staff_id`
 * (`ChangeAssetCustodianAction`) — a STAFF member, never a student —
 * and carries no expected-return-date/overdue concept at all. Sports
 * kit is issued to individual learners with a season-end return
 * expectation, which fits neither shape. This table reuses
 * `fixed_assets` as the item reference (no duplicate asset register)
 * but is its own new, additive table for the person-level issue/
 * return/overdue lifecycle `Modules\Stores` doesn't have — modelled on
 * this same book's own `Modules\Security`'s `contractor_site_visits`
 * precedent (a genuine gap filled the same way, one module prior).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained();
            $table->foreignId('asset_id')->constrained('fixed_assets');
            $table->foreignId('student_id')->constrained();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users');
            $table->date('expected_return_on')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('condition_on_return', 20)->nullable();
            $table->string('notes', 255)->nullable();

            $table->index(['school_id', 'activity_id', 'returned_at'], 'equipment_issues_school_activity_returned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_issues');
    }
};
