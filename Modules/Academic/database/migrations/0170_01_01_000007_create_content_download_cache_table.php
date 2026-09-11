<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book K ACA-08 §2/§3. Tracks what's queued for offline on a device —
 * not `BelongsToSchool` (same reasoning as `files`: every write
 * supplies `user_id`/`content_item_id` explicitly and a device can
 * carry cached content across a school year boundary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_download_cache', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('downloaded_at')->nullable();
            $table->string('device_id', 120)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'content_item_id', 'device_id'], 'content_download_cache_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_download_cache');
    }
};
