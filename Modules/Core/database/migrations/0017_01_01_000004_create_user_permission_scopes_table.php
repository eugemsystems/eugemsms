<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2 extension (2026-09-12, user-requested): direct
 * permission grants to a specific user, independent of any role — the
 * scope layer mirrors `role_permission_scopes` exactly, just keyed on
 * `user_id` instead of `role_id`, and additionally on `school_id` since
 * a tenant-wide user can hold different direct grants in different
 * schools (the underlying `model_has_permissions` row spatie's own
 * `$user->givePermissionTo()` writes is already school-scoped via the
 * "teams" feature — this table only carries the extra scope dimension
 * spatie has no column for).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20);

            $table->unique(['user_id', 'permission_id', 'school_id'], 'user_permission_scopes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_scopes');
    }
};
