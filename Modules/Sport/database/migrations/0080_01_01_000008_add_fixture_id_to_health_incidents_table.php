<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-07 §3/BR-OPS-07-012/AC-OPS-07-005 — a learner injured at
 * a fixture raises a real `BRD-06` `HealthIncident` (via the
 * already-shipped `Modules\Welfare\Domain\Actions\
 * RecordHealthIncidentAction`) "linked to the fixture". That table
 * (Book G, already shipped) has no structured link to anything
 * outside `Modules\Welfare` — `activity_at_time` is free text. This
 * additive, nullable, default-null column is the structured link
 * AC-OPS-07-005 needs; it changes no existing behaviour and every
 * other consumer of `health_incidents` is unaffected. See
 * `RecordFixtureInjuryAction` and the owning provider's own docblock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_incidents', function (Blueprint $table): void {
            $table->foreignId('fixture_id')->nullable()->after('admission_id')->constrained('fixtures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('health_incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fixture_id');
        });
    }
};
