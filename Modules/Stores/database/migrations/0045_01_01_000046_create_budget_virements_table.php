<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_virements', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained('budgets');
            $table->foreignId('from_line_id')->constrained('budget_lines');
            $table->foreignId('to_line_id')->constrained('budget_lines');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->text('reason');
            $table->string('status', 20);
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_virements');
    }
};
