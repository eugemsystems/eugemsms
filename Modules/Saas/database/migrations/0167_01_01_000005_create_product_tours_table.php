<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2 — a code-owned tour definition, not tenant data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tours', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('persona', 20);
            $table->json('steps');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tours');
    }
};
