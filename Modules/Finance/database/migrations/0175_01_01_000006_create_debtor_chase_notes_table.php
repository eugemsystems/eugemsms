<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §5 `Finance\Debtors\Workbench` — "a prioritised chase
 * list with call notes and outcomes". Not in the spec's own §2 data
 * model (the workbench is a view over `invoices`/receipts, but the
 * notes/outcomes a bursar records while chasing a debtor need
 * somewhere to live); this table is that minimal addition, append-only
 * like every other financial-adjacent log in this book — a note is
 * never edited or deleted, a correction is a new note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debtor_chase_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('outcome', 40);
            $table->text('note');
            $table->date('next_action_on')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debtor_chase_notes');
    }
};
