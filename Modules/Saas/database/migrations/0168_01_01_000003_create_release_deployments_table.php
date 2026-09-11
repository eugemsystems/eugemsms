<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-02 §2/BR-SAA-02-005 — a canary runs against named tenants
 * before general release; rollback stays available until the
 * deployment is explicitly confirmed stable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_deployments', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 20);
            $table->string('deployment_stage', 20);
            $table->json('canary_tenant_ids')->nullable();
            $table->string('migration_status', 20);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('rollback_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_deployments');
    }
};
