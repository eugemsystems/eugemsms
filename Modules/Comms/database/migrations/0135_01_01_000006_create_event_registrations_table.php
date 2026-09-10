<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2/BR-COM-06-006/007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('capacity')->nullable();
            $table->boolean('requires_ticket')->default(false);
            $table->bigInteger('ticket_price_minor')->nullable();
            $table->char('ticket_currency', 3)->nullable();
            $table->foreignId('fee_component_id')->nullable()->constrained('fee_components');
            $table->timestamp('rsvp_deadline')->nullable();
            $table->smallInteger('registered_count')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'calendar_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
