<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2/§7/BR-FIN-09-014 ⭐ — blind by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('store_id')->constrained('stores');
            $table->string('take_number', 40);
            $table->string('take_type', 20);
            $table->date('scheduled_for');
            $table->date('counted_on')->nullable();
            $table->string('status', 20);
            $table->boolean('is_blind_count')->default(true);
            $table->bigInteger('total_variance_minor')->nullable();
            $table->smallInteger('line_count')->default(0);
            $table->smallInteger('variance_line_count')->default(0);
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->timestamps();

            $table->unique(['school_id', 'take_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
