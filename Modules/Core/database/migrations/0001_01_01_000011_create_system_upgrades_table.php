<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->string('from_version', 20);
            $table->string('to_version', 20);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->default('running');
            $table->string('backup_reference')->nullable();
            $table->json('migrations_run')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_upgrades');
    }
};
