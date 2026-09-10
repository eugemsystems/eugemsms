<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-02 §2/BR-COM-02-002.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('automation_rules');
            $table->timestamp('ran_at');
            $table->integer('records_scanned')->default(0);
            $table->integer('records_matched')->default(0);
            $table->integer('notifications_dispatched')->default(0);
            $table->integer('duration_ms')->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();

            $table->index(['school_id', 'rule_id', 'ran_at'], 'scan_runs_rule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_runs');
    }
};
