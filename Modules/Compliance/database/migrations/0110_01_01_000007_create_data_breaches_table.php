<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-011 ⭐/012 (AC-CMP-03-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breaches', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamp('detected_at');
            $table->timestamp('occurred_at')->nullable();
            $table->string('breach_type', 40);
            $table->text('description');
            $table->json('data_categories');
            $table->integer('records_affected')->nullable();
            $table->integer('subjects_affected')->nullable();
            $table->boolean('includes_minors')->default(false);
            $table->string('severity', 20);
            $table->text('containment_actions')->nullable();
            $table->timestamp('contained_at')->nullable();
            $table->boolean('authority_notified')->default(false);
            $table->timestamp('authority_notified_at')->nullable();
            $table->boolean('subjects_notified')->default(false);
            $table->timestamp('subjects_notified_at')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('remedial_actions')->nullable();
            $table->string('status', 20);
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'status', 'severity'], 'data_breaches_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
    }
};
