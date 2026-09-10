<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Book B FIN-06 §2. Seeded, global — the DB-backed display/formatting
 * config for the closed set `Modules\Core\Domain\Support\Currency`
 * already defines as the only codes `Money` may be constructed with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 60);
            $table->string('symbol', 10);
            $table->tinyInteger('minor_unit_digits')->default(2);
            $table->string('display_format', 40)->default('{symbol}{amount}');
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
        });

        DB::table('currencies')->insert([
            ['code' => 'USD', 'name' => 'United States Dollar', 'symbol' => '$', 'minor_unit_digits' => 2, 'display_format' => '{symbol}{amount}', 'is_active' => true, 'sort_order' => 10],
            ['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG', 'minor_unit_digits' => 2, 'display_format' => '{symbol} {amount}', 'is_active' => true, 'sort_order' => 20],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
