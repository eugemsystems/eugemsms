<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-02 §2/BR-SAA-02-006 — targeting is explicit
 * (`target_tenant_ids`), never inferred; `null` means every tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->text('body');
            $table->string('severity', 20);
            $table->json('target_tenant_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('posted_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_announcements');
    }
};
