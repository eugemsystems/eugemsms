<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K PPL-06 §2/BR-PPL-06-010. `raised_amount_minor` is derived
 * from `donations` — never directly updatable — see
 * `RecordDonationAction`, the only writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('purpose');
            $table->unsignedBigInteger('target_amount_minor');
            $table->unsignedBigInteger('raised_amount_minor')->default(0);
            $table->char('currency', 3);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('income_account_id')->constrained('accounts');
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_campaigns');
    }
};
