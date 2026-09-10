<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-01 §2/§4 ⭐/BR-BRD-01-001/002. `gender` is the hard,
 * no-override boarding constraint the whole allocation engine is
 * built around. `capacity` is written by `RecalculateHostelCapacityAction`
 * from active beds — never entered by hand (BR-BRD-01-002).
 * `house_id` is a forward reference to a `houses` table that does not
 * exist yet (`OPS-07`, Book H) — no FK, matching the
 * `ProjectBrief.brief_document_id` precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostels', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('gender', 10);
            $table->foreignId('section_id')->nullable()->constrained('school_sections');
            $table->unsignedBigInteger('house_id')->nullable();
            $table->foreignId('housemaster_staff_id')->nullable()->constrained('staff');
            $table->foreignId('matron_staff_id')->nullable()->constrained('staff');
            $table->foreignId('deputy_staff_id')->nullable()->constrained('staff');
            $table->smallInteger('capacity')->default(0);
            $table->string('building', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('has_sick_bay')->default(false);
            $table->boolean('has_prep_room')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'hostels_code_unique');
            $table->index(['school_id', 'gender', 'is_active'], 'hostels_gender_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostels');
    }
};
