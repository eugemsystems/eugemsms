<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->dateTime('value_date')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'entity_id']);
            $table->index(['school_id', 'entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
