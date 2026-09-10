<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-05 §2, Sanctum's table extended with device + school
 * binding. Written by hand rather than via `laravel/sanctum`'s own
 * publishable migration so the extra columns land in the same table on
 * first create (`Sanctum::usePersonalAccessTokenModel()` points at
 * `Modules\Core\Models\PersonalAccessToken`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name', 150);
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 120)->nullable();
            $table->string('device_platform', 30)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id', 'revoked_at'], 'personal_access_tokens_revoked_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
