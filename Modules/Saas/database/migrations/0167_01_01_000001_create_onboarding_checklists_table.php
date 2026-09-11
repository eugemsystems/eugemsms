<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2/BR-SAA-03-001. One checklist per school —
 * `assigned_success_manager` is a vendor user (`users.id`, `user_type
 * = 'vendor'`), never a school `Staff` row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->date('target_go_live_date')->nullable();
            $table->json('steps');
            $table->string('status', 20);
            $table->foreignId('assigned_success_manager')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_checklists');
    }
};
