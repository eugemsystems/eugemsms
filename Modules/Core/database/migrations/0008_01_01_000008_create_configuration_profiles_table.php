<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-04 §2/BR-CORE-04-014: carries structure and configuration
 * only — never learner, staff, or financial data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_profiles', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('source_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->json('payload');
            $table->string('version', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_profiles');
    }
};
