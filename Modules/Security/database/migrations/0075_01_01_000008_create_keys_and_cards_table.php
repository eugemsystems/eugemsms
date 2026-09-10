<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-006.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keys_and_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('identifier', 60);
            $table->string('item_type', 20);
            $table->string('description', 200);
            $table->string('opens_location', 200)->nullable();
            $table->boolean('is_master')->default(false);
            $table->string('status', 20);

            $table->unique(['school_id', 'identifier'], 'keys_and_cards_school_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keys_and_cards');
    }
};
