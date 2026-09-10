<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J INT-04 §2/BR-INT-04-011. Per-tenant usage; never queryable
 * across tenants (enforced by `BelongsToSchool`, same as every other
 * tenant table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('api_clients');
            $table->string('endpoint', 200);
            $table->string('method', 10);
            $table->smallInteger('status_code');
            $table->integer('duration_ms')->nullable();
            $table->timestamp('occurred_at', 6);

            $table->index(['school_id', 'client_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_log');
    }
};
