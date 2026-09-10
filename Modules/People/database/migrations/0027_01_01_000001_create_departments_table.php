<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-04 §2. `head_staff_id` is a plain nullable column here,
 * not yet a foreign key — `staff` doesn't exist until this batch's
 * next migration. The constraint is added once it does, in
 * `0027_01_01_000010_add_head_staff_foreign_key_to_departments_table.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('type', 20);
            $table->unsignedBigInteger('head_staff_id')->nullable();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
