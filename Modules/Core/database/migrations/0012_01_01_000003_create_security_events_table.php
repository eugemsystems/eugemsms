<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('severity', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['severity', 'is_reviewed', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
