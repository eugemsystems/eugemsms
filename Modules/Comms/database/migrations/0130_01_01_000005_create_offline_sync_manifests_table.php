<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-03 §4/BR-COM-03-010. Sync is a caching strategy, never a
 * second authorisation layer — `examination_papers` never appears as
 * a `data_category` a manifest can request; see the module's own
 * scope note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_manifests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('data_category', 40);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_token', 120)->nullable();
            $table->integer('cache_ttl_hours')->default(24);
            $table->timestamps();

            $table->unique(['user_id', 'data_category'], 'offline_sync_manifests_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_manifests');
    }
};
