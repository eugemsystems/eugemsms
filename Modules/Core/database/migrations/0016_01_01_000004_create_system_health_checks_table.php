<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('check_key', 60);
            $table->string('status', 20);
            $table->string('value', 120)->nullable();
            $table->string('threshold', 120)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at');

            $table->index(['check_key', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_checks');
    }
};
