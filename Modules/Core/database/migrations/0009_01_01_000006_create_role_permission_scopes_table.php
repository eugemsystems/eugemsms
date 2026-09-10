<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2. The scope layer over spatie's plain role↔permission
 * grant — BR-CORE-05-014/015: a permission check resolves
 * (user, active_school, permission, scope), and when a user holds the
 * same permission at multiple scopes through different roles, the
 * widest wins (school > section > assigned > own).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20);

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_scopes');
    }
};
