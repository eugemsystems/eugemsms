<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-07 §2/BR-CORE-07-002/005. `approver_type` decides which
 * of `approver_role_id`/`approver_user_id`/`dynamic_resolver` is used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chain_id')->constrained('approval_chains')->cascadeOnDelete();
            $table->smallInteger('step_number');
            $table->string('name', 120);
            $table->string('approver_type', 20);
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dynamic_resolver', 120)->nullable();
            $table->string('mode', 20);
            $table->tinyInteger('required_approvals')->unsigned()->default(1);
            $table->json('condition_rules')->nullable();
            $table->smallInteger('escalate_after_hours')->nullable();
            $table->foreignId('escalate_to_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('can_reject')->default(true);
            $table->boolean('can_return')->default(true);
            $table->boolean('requires_comment')->default(false);

            $table->unique(['chain_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
