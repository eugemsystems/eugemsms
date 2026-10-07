<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §5 (`Academic\Marks\Moderate`, `academic.result.moderate`). The spec's own
 * `assessments.status` enum already names `moderated` as a real state (see
 * `0033_01_01_000005_create_assessments_table.php` and `PublishAssessmentAction`'s own docblock,
 * which accepted it as a valid pre-publish status with no action ever setting it) — this adds the
 * audit columns that state needs, matching `submitted_by`/`submitted_at` and
 * `approved_by`/`approved_at`'s own shape exactly rather than overloading either of those for a
 * conceptually different event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('moderated_by')->nullable()->after('submitted_at')->constrained('users');
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->text('moderation_note')->nullable()->after('moderated_at');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn('moderation_note');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn('moderated_at');
        });
    }
};
