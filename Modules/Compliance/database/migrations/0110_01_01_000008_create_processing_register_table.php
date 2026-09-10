<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-013. Each module registers its own
 * entries — this table has no `unique` constraint on activity name
 * since more than one module may legitimately describe similar
 * activities differently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_register', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('activity_name', 200);
            $table->text('purpose');
            $table->string('lawful_basis', 40);
            $table->json('data_categories');
            $table->json('subject_categories');
            $table->json('recipients')->nullable();
            $table->foreignId('retention_schedule_id')->nullable()->constrained('retention_schedules');
            $table->boolean('involves_minors')->default(false);
            $table->boolean('is_special_category')->default(false);
            $table->text('security_measures')->nullable();
            $table->string('owning_module', 20)->nullable();
            $table->date('last_reviewed_on')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'owning_module'], 'processing_register_module_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_register');
    }
};
