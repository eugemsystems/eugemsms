<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Book A CORE-05 §2. `users` is global identity, not school-scoped —
 * `tenant_id` places it one level above `BelongsToSchool`. Spec marks
 * `first_name`/`last_name` NOT NULL, but this app's `users` table
 * predates CORE-05 (Fortify's `name` column, already relied on by the
 * login/profile views) and this environment has no `doctrine/dbal`
 * dependency to `->change()` a column nullable→NOT NULL after backfill.
 * Columns are added nullable and backfilled from `name` below;
 * `CreateUserAction` enforces non-blank first/last name at the
 * application layer. `name` itself is kept, not replaced — it stays the
 * single source Fortify's views read, kept in sync by `CreateUserAction`/
 * `UpdateProfileAction` as "{first} {last}".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('ulid', 26)->nullable()->unique()->after('id');
            $table->foreignId('tenant_id')->nullable()->after('ulid')->constrained()->nullOnDelete();
            $table->string('first_name', 80)->nullable()->after('name');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->string('other_names', 120)->nullable()->after('last_name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('username', 60)->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('password');
            $table->string('user_type', 20)->default('staff')->after('avatar_path');
            $table->string('locale', 10)->default('en_ZW')->after('user_type');
            $table->string('status', 20)->default('active')->after('locale');
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_confirmed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->smallInteger('failed_login_count')->default(0)->after('last_login_ip');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            $table->foreignId('created_by')->nullable()->after('locked_until')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
            $table->unique(['tenant_id', 'username']);
            $table->index(['tenant_id', 'user_type', 'status']);
        });

        DB::table('users')->select('id', 'name')->orderBy('id')->each(function (object $user): void {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

            DB::table('users')->where('id', $user->id)->update([
                'ulid' => (string) Str::ulid(),
                'first_name' => $parts[0] ?? $user->name,
                'last_name' => $parts[1] ?? '-',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropUnique(['tenant_id', 'phone']);
            $table->dropUnique(['tenant_id', 'username']);
            $table->dropIndex(['tenant_id', 'user_type', 'status']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'ulid', 'first_name', 'last_name', 'other_names', 'phone', 'username',
                'avatar_path', 'user_type', 'locale', 'status', 'must_change_password',
                'password_changed_at', 'phone_verified_at', 'last_login_at', 'last_login_ip',
                'failed_login_count', 'locked_until',
            ]);
        });
    }
};
