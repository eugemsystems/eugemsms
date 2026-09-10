<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-04 §2/BR-CORE-04-006. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_change_log', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 120);
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('changed_at');

            $table->index(['setting_key', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_change_log');
    }
};
