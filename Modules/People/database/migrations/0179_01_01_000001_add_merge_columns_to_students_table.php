<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-01 BR-PPL-01-010. Same shape as guardians' own merge columns
 * (`0176_01_01_000020_add_merge_columns_to_guardians_table.php`): a merged
 * duplicate learner is kept, never deleted, pointing at the record that
 * absorbed it. Financial records are deliberately NOT reassigned here (see
 * `MergeDuplicateStudentsAction`'s own docblock) -- this column exists so a
 * caller can still find "what did this learner's history used to be filed
 * under" without rewriting a single journal_lines/invoices row, honouring
 * CLAUDE.md's append-only-ledger rule in place of the spec's own literal
 * "reassigns every financial record" wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('status')->constrained('students')->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_id');
            $table->foreignId('merged_by')->nullable()->after('merged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_by');
            $table->dropColumn('merged_at');
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
