<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-04 §3/BR-CMP-04-005. `CheckDocumentExpiryAction` treats
 * `contracts` the same way it treats `statutory_documents` — both
 * need the same lead-time column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->smallInteger('renewal_lead_days')->default(60)->after('expires_on');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('renewal_lead_days');
        });
    }
};
