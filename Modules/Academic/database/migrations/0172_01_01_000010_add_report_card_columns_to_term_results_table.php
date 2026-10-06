<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book D ACA-05 §3/§4 (BR-ACA-05-010/015). A report card is regenerated as a
 * new version whenever a published mark is amended; the version and when it
 * was generated live beside the stored document it points at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_results', function (Blueprint $table): void {
            $table->unsignedSmallInteger('report_version')->default(0)->after('report_document_id');
            $table->timestamp('report_generated_at')->nullable()->after('report_version');
        });
    }

    public function down(): void
    {
        Schema::table('term_results', function (Blueprint $table): void {
            $table->dropColumn(['report_version', 'report_generated_at']);
        });
    }
};
