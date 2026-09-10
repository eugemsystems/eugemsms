<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flag_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_flag_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            $table->boolean('is_enabled');
            $table->timestamps();

            $table->unique(['feature_flag_id', 'scope_type', 'scope_id'], 'feature_flag_overrides_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
    }
};
