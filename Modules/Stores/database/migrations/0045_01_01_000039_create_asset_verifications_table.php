<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-011/012 ⭐. One row per asset per
 * verification round, created up front for every asset due — an
 * asset that never gets scanned stays `pending`, visibly outstanding,
 * rather than silently absent from the round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_verifications', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('verification_round', 40);
            $table->foreignId('asset_id')->constrained('fixed_assets');
            $table->date('verified_on')->nullable();
            $table->boolean('found')->nullable();
            $table->boolean('location_confirmed')->nullable();
            $table->string('actual_location', 150)->nullable();
            $table->string('condition_observed', 20)->nullable();
            $table->string('scan_method', 20)->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('discrepancy_note')->nullable();
            $table->string('status', 20);

            $table->unique(['verification_round', 'asset_id']);
            $table->index(['school_id', 'verification_round', 'status'], 'verifications_round_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_verifications');
    }
};
