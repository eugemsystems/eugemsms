<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2. Extends spatie/laravel-permission's stock `roles`/
 * `permissions` tables with the columns the spec's schema adds on top:
 * a public ULID, display metadata, and the `is_system` guard against
 * deleting/renaming a shipped role template (BR-CORE-05-013).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->char('ulid', 26)->nullable()->unique()->after('id');
            $table->string('display_name', 120)->after('name');
            $table->string('description', 255)->nullable()->after('display_name');
            $table->boolean('is_system')->default(false)->after('guard_name');
            $table->boolean('is_vendor_only')->default(false)->after('is_system');
            $table->string('category', 30)->default('custom')->after('is_vendor_only');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('module_code', 20)->after('name');
            $table->string('resource', 60)->after('module_code');
            $table->string('action', 40)->after('resource');
            $table->string('description', 255)->nullable()->after('action');
            $table->boolean('is_dangerous')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['ulid', 'display_name', 'description', 'is_system', 'is_vendor_only', 'category']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn(['module_code', 'resource', 'action', 'description', 'is_dangerous']);
        });
    }
};
