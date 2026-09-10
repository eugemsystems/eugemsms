<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 150);
            $table->string('slug', 60)->unique();
            $table->string('type', 30)->default('independent');
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->char('country', 2)->default('ZW');
            $table->string('status', 20)->default('trial');
            $table->boolean('is_group_reporting_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
