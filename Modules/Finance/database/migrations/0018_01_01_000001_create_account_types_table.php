<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-01 §2. Seeded, not user-editable — the five account types
 * are a closed, universal set (double-entry accounting doesn't get a
 * sixth), so they're inserted directly here rather than through a
 * code-owns-the-list registry like `FileCategoryRegistry`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->char('normal_balance', 2);
            $table->string('statement', 20);
            $table->smallInteger('sort_order');
        });

        DB::table('account_types')->insert([
            ['code' => 'ASSET', 'name' => 'Asset', 'normal_balance' => 'DR', 'statement' => 'balance_sheet', 'sort_order' => 10],
            ['code' => 'LIABILITY', 'name' => 'Liability', 'normal_balance' => 'CR', 'statement' => 'balance_sheet', 'sort_order' => 20],
            ['code' => 'EQUITY', 'name' => 'Equity', 'normal_balance' => 'CR', 'statement' => 'balance_sheet', 'sort_order' => 30],
            ['code' => 'INCOME', 'name' => 'Income', 'normal_balance' => 'CR', 'statement' => 'income_statement', 'sort_order' => 40],
            ['code' => 'EXPENSE', 'name' => 'Expense', 'normal_balance' => 'DR', 'statement' => 'income_statement', 'sort_order' => 50],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_types');
    }
};
