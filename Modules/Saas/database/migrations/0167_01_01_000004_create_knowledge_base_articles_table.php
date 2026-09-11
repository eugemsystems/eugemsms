<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book J SAA-03 §2 — public/in-app reference content, not tenant data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->string('title', 200);
            $table->string('module_code', 20)->nullable();
            $table->longText('content');
            $table->integer('view_count')->default(0);
            $table->integer('helpful_votes')->default(0);
            $table->date('last_reviewed_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_articles');
    }
};
