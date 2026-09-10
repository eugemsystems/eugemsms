<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-10 §2/BR-CORE-10-007. Sensitive-category files only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_access_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_access_log');
    }
};
