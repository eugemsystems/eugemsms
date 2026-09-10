<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-07 §2/BR-CORE-07-009. `approvable_type` null means the
 * delegation covers every type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users');
            $table->foreignId('delegate_id')->constrained('users');
            $table->string('approvable_type', 60)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
