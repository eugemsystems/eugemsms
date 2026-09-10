<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book E ACA-03 §5/BR-ACA-03-019. `ACA-04`'s own `attendance_sessions`
 * migration didn't add this column — `ACA-03` (which needs it to
 * stamp the teaching slot's teacher, and to move it when a
 * substitution is assigned) didn't exist yet. Added now that it does,
 * closing that half of the interface the spec's ACA-04 §5 stub named.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->foreignId('staff_id')->nullable()->after('subject_id')->constrained('staff');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
