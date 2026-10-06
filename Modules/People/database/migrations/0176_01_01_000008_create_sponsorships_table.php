<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3. An organisation's funding programme for a number of learners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorships', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians');
            $table->string('name', 200);
            $table->string('sponsorship_type', 30);
            $table->unsignedBigInteger('budget_minor')->nullable();
            $table->char('budget_currency', 3)->nullable();
            $table->unsignedBigInteger('committed_minor')->default(0);
            $table->unsignedBigInteger('invoiced_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->unsignedSmallInteger('max_beneficiaries')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('contract_document_id')->nullable();
            $table->string('status', 20);
            $table->string('contact_person', 150)->nullable();
            $table->string('reporting_frequency', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorships');
    }
};
