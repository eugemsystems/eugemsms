<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-05 §2/BR-BRD-05-001/002/003/004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_issued_items', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('issuable_item_id')->constrained('issuable_items');
            $table->string('tag_reference', 40)->nullable();
            $table->smallInteger('quantity')->default(1);
            $table->string('condition_at_issue', 20);
            $table->date('issued_on');
            $table->foreignId('issued_by')->constrained('users');
            $table->date('returned_on')->nullable();
            $table->string('condition_at_return', 20)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->string('status', 20);
            $table->bigInteger('charge_minor')->nullable();
            $table->unsignedBigInteger('ad_hoc_charge_id')->nullable();
            $table->string('notes', 255)->nullable();

            $table->index(['school_id', 'student_id', 'status'], 'learner_issued_items_student_idx');
            $table->index(['school_id', 'term_id', 'status'], 'learner_issued_items_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_issued_items');
    }
};
