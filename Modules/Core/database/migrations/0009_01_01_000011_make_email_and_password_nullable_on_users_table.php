<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2: `email VARCHAR(150) NULL` (many parents have no
 * email) and `password VARCHAR(255) NULL` (phone-OTP-only accounts).
 * The stock Laravel `users` migration made both NOT NULL. Laravel 13's
 * `->change()` no longer needs `doctrine/dbal` (removed in the
 * framework itself), so no new dependency is needed for this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
