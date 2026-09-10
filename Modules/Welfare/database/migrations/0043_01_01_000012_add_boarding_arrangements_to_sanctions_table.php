<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book G BRD-07 §4/BR-BRD-07-014/AC-BRD-07-007 — a boarder's
 * supervision/transport-home arrangements, recorded as part of the
 * sanction before it takes effect. Not in the spec's own §2 table
 * listing, but required by its own §4 business rule; added the same
 * way `student_guardian.may_authorise_exeat`/`may_authorise_medical`
 * were added when a later rule needed a column §2 hadn't listed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctions', function (Blueprint $table): void {
            $table->text('boarding_arrangements')->nullable()->after('conditions');
        });
    }

    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table): void {
            $table->dropColumn('boarding_arrangements');
        });
    }
};
