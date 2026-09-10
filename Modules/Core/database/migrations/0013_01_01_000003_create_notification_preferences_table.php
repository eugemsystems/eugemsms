<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_key', 80)->nullable();
            $table->string('channel', 20);
            $table->boolean('is_enabled')->default(true);

            $table->unique(['user_id', 'notification_key', 'channel'], 'notification_preferences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
