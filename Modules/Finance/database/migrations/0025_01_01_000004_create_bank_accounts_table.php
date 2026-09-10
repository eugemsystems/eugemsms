<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained('accounts');
            $table->string('bank_name', 120);
            $table->string('account_name', 200);
            $table->string('account_number', 60);
            $table->string('branch', 120)->nullable();
            $table->char('currency', 3);
            $table->string('account_type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
