<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-09 §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->foreignId('default_expense_account_id')->nullable()->constrained('accounts');
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }
};
