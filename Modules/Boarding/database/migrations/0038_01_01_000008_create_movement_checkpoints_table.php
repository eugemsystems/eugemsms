<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-02 §2/BR-BRD-02-017.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('checkpoint_type', 30);
            $table->string('hardware_device_id', 80)->nullable();
            $table->boolean('is_boundary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code'], 'movement_checkpoints_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_checkpoints');
    }
};
