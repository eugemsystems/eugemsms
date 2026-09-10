<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-04 §2. A physical or logical cash point — 'BURSARY-1',
 * 'TUCKSHOP'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tills', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('location', 120)->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('cash_account_id')->constrained('accounts');
            $table->json('accepted_currencies');
            $table->json('accepted_tenders');
            $table->boolean('is_fiscalised')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tills');
    }
};
