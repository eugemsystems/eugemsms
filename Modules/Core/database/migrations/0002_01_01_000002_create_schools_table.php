<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenancy anchor (Volume 1 ADR-003, Book A CORE-02 §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('short_name', 60)->nullable();
            $table->string('centre_number', 20)->nullable()->unique();
            $table->string('emis_code', 30)->nullable();
            $table->string('category', 30)->default('private');
            $table->string('responsible_authority', 150)->nullable();
            $table->string('band', 20)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('address_line_1', 200)->nullable();
            $table->string('address_line_2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('motto', 200)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('crest_path')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->char('primary_colour', 7)->default('#1a3a5c');
            $table->char('secondary_colour', 7)->nullable();
            $table->char('base_currency', 3)->default('USD');
            $table->string('timezone', 50)->default('Africa/Harare');
            $table->string('locale', 10)->default('en_ZW');
            // Deferred FK: users.id exists, but head_user_id is set after the
            // school's first admin is provisioned (CORE-01) — nullable, no
            // constrained() to avoid an ordering dependency on that flow.
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->date('opened_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
