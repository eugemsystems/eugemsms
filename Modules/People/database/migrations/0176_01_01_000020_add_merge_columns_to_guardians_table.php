<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 BR-PPL-03-023. A merged duplicate guardian is kept, never deleted: it points at the
 * guardian that absorbed it, so history that once named it can still be traced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('status')->constrained('guardians')->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_id');
            $table->foreignId('merged_by')->nullable()->after('merged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_by');
            $table->dropColumn('merged_at');
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
