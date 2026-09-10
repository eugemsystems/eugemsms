<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §4/BR-PPL-04-021 (AC-PPL-04-008). The spec names the
 * clearance checklist ("keys, assets, laptop, library items, staff
 * advances settled") as a business rule but doesn't give it a table
 * of its own in §2's data model — this is a new table filling that
 * gap, following the spec's own general pattern (a `ulid`-less
 * simple record here, since nothing outside this staff member's own
 * exit process ever references a checklist by id). One row per staff
 * member's exit; `items` holds the named checks as a JSON array of
 * `{code, label, is_cleared, cleared_by, cleared_at}`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_exit_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->unique()->constrained('staff')->cascadeOnDelete();
            $table->timestamp('initiated_at');
            $table->foreignId('initiated_by')->constrained('users');
            $table->json('items');
            $table->timestamp('final_pay_released_at')->nullable();
            $table->foreignId('final_pay_released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_exit_checklists');
    }
};
