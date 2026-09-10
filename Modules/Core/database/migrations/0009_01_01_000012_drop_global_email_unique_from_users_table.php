<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 BR-CORE-05-002: email is unique per tenant, not
 * globally — two tenants may legitimately have the same parent. The
 * stock Laravel `users` migration left a global `users_email_unique`
 * index in place; `0009_01_01_000001` already added the correct
 * `(tenant_id, email)` compound unique alongside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email');
        });
    }
};
