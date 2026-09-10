<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-001/003 (AC-CMP-04-001) — APPEND-ONLY.
 * `policy_version` is frozen on the row at acknowledgement time — a
 * new policy version never retroactively changes what an earlier
 * acknowledgement actually agreed to, mirroring `consents.notice_version`'s
 * own doctrine in CMP-03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('policies');
            $table->string('policy_version', 20);
            $table->string('acknowledged_by_type', 20);
            $table->unsignedBigInteger('acknowledged_by_id');
            $table->timestamp('acknowledged_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 30);
            $table->timestamps();

            $table->unique(['school_id', 'policy_id', 'policy_version', 'acknowledged_by_type', 'acknowledged_by_id'], 'policy_acks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acknowledgements');
    }
};
