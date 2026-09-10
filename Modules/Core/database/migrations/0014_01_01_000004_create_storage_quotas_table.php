<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_quotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('quota_bytes');
            $table->bigInteger('used_bytes')->default(0);
            $table->tinyInteger('warn_at_percent')->unsigned()->default(85);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_quotas');
    }
};
