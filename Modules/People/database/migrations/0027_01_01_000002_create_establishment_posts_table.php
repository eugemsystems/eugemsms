<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2/BR-PPL-04-004. `filled_count` is maintained by
 * `FillEstablishmentPostAction`/`VacateEstablishmentPostAction` — never
 * derived by counting `staff.post_id` on read, so a post can be
 * queried for vacancy without joining `staff` at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title', 120);
            $table->string('grade', 30)->nullable();
            $table->smallInteger('approved_count')->default(1);
            $table->smallInteger('filled_count')->default(0);
            $table->boolean('is_teaching')->default(false);
            $table->boolean('is_active')->default(true);

            $table->index(['school_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_posts');
    }
};
