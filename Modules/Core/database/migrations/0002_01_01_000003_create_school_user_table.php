<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-school user assignment (Volume 1 §3.2, Book A CORE-02 §2). A user
 * is one identity row globally; roles and the active session are resolved
 * per (user, school).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_user');
    }
};
