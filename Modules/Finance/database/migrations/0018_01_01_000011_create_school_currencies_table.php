<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_currencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_accepted_for_payment')->default(true);
            $table->integer('rounding_increment_minor')->default(1);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_currencies');
    }
};
