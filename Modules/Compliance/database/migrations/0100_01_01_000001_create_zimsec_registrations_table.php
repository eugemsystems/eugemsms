<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-013. One row per exam level/series a
 * school is registering candidates for. `total_fees_minor` is always
 * recomputed fresh from `zimsec_candidates` by `ReconcileZimsecFeesAction`
 * — never independently incremented — while `collected_minor` and
 * `remitted_minor` are explicit bursar-recorded figures (BR-CMP-01-007's
 * "amount remitted to ZIMSEC" has no GL/invoice trail to derive from:
 * `ad_hoc_charges` — see `zimsec_candidates.ad_hoc_charge_id` — is
 * never invoiced or posted to the GL anywhere in this codebase yet,
 * a pre-existing FIN-02/FIN-03 gap this module does not attempt to
 * close).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zimsec_registrations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('examination_session_id')->nullable()->constrained('examination_sessions');
            $table->string('exam_level', 20);
            $table->string('exam_series', 20);
            $table->string('centre_number', 20);
            $table->date('registration_opens_on')->nullable();
            $table->date('registration_closes_on');
            $table->smallInteger('candidate_count')->default(0);
            $table->smallInteger('validated_count')->default(0);
            $table->smallInteger('error_count')->default(0);
            $table->bigInteger('total_fees_minor')->default(0);
            $table->bigInteger('collected_minor')->default(0);
            $table->bigInteger('remitted_minor')->default(0);
            $table->char('currency', 3);
            $table->unsignedBigInteger('export_file_id')->nullable();
            $table->string('status', 20);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->string('zimsec_reference', 80)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'exam_level', 'exam_series'], 'zimsec_registrations_series_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zimsec_registrations');
    }
};
