<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book C PPL-03 §3. Deliberately narrower than the spec's literal SQL
 * — `national_registration_no` (+ hash), date of birth, the alternate/
 * WhatsApp phone numbers, the address block beyond `country`,
 * employment fields, alumni tracking, and soft-deletes are all
 * screens/duplicate-detection concerns this pass does not build (see
 * `.ai/rules/academic.md`'s note on the same style of cut for
 * `ACA-01`/`ACA-02`). What remains is exactly what `FIN-03`'s
 * liability resolution and invoicing need: who a guardian is, and how
 * to reach them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_type', 20);
            $table->string('title', 20)->nullable();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('organisation_name', 200)->nullable();
            $table->string('organisation_type', 30)->nullable();
            $table->string('primary_phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->char('country', 2)->default('ZW');
            $table->string('preferred_language', 20)->default('en');
            $table->string('preferred_channel', 20)->default('whatsapp');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'last_name', 'first_name']);
            $table->index(['school_id', 'primary_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
