<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deferred FK — see the docblock on
 * `0021_01_01_000005_create_learner_fee_assignments_table.php`.
 * `billing_runs` now exists, so the constraint can be added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_fee_assignments', function (Blueprint $table): void {
            $table->foreign('billing_run_id')->references('id')->on('billing_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('learner_fee_assignments', function (Blueprint $table): void {
            $table->dropForeign(['billing_run_id']);
        });
    }
};
