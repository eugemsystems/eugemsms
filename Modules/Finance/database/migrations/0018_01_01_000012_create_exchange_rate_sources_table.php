<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 120);
            $table->boolean('is_automatic')->default(false);
            $table->string('endpoint_url', 255)->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->smallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_sources');
    }
};
