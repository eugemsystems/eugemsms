<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deferred FK — see the docblock on
 * `0027_01_01_000001_create_departments_table.php`. `staff` now
 * exists, so the constraint can be added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->foreign('head_staff_id')->references('id')->on('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropForeign(['head_staff_id']);
        });
    }
};
