<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-02 §2 — the public-facing incident status page's own
 * backing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_status_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title', 200);
            $table->json('affected_components');
            $table->string('severity', 20);
            $table->string('status', 20);
            $table->json('updates');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_status_entries');
    }
};
