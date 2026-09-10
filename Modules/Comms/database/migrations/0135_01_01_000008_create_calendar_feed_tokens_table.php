<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §4/BR-COM-06-008. The spec names a "token-scoped"
 * public iCal feed (`GET /api/v1/calendar.ics`) without giving the
 * token a table — this is the natural storage for it, the same kind
 * of documented schema completion already made for `privacy_notices`
 * (CMP-03) and `contracts` (CMP-04). The token is opaque and scoped
 * to exactly one viewer's own audience at issue time — never a
 * broader "whole school" grant by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_feed_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->char('token', 64)->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('audience_scope', 20);
            $table->unsignedBigInteger('audience_scope_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feed_tokens');
    }
};
