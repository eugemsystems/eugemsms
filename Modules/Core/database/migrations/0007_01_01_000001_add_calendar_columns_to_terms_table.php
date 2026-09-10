<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-03 §2. Additive — `terms` already migrated with Part 1's
 * minimal column set; a real deployment may already have rows, so this
 * extends rather than rewrites the original create migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->date('half_term_starts_on')->nullable()->after('ends_on');
            $table->date('half_term_ends_on')->nullable()->after('half_term_starts_on');
            $table->smallInteger('teaching_days')->nullable()->after('half_term_ends_on');
            $table->date('fee_due_on')->nullable()->after('teaching_days');
            $table->date('results_due_on')->nullable()->after('fee_due_on');
            $table->date('reports_release_on')->nullable()->after('results_due_on');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn([
                'half_term_starts_on',
                'half_term_ends_on',
                'teaching_days',
                'fee_due_on',
                'results_due_on',
                'reports_release_on',
            ]);
        });
    }
};
