<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 80);
            $table->foreignId('debit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('debit_resolver', 80)->nullable();
            $table->string('credit_resolver', 80)->nullable();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_rules');
    }
};
