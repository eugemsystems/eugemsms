<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-03 §2/BR-FIN-03-017. An instalment schedule against a
 * party's (guardian's) total balance, not against one invoice — a
 * family routinely spreads several invoices' worth of debt across one
 * plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained();
            $table->string('party_type', 30);
            $table->unsignedBigInteger('party_id');
            $table->bigInteger('total_minor');
            $table->char('currency', 3);
            $table->tinyInteger('instalment_count');
            $table->string('status', 20);
            $table->unsignedBigInteger('agreement_document_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->tinyInteger('breach_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['school_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
