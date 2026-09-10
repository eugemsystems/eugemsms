<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-02 §2/BR-CMP-02-002. One row per check per school,
 * refreshed (not appended) each time `RunDataQualityChecksAction`
 * runs — the fields that cause Ministry rejection, caught here first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 60);
            $table->string('entity_type', 40);
            $table->integer('affected_count');
            $table->json('affected_ids')->nullable();
            $table->string('severity', 20);
            $table->timestamp('last_checked_at');

            $table->unique(['school_id', 'check_key'], 'data_quality_checks_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_checks');
    }
};
