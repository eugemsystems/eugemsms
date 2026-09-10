<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book A CORE-09 §2. `school_id` nullable — NULL is the system
 * default template for a key/channel/locale, overridable per school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('channel', 20);
            $table->string('locale', 10)->default('en_ZW');
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('provider_template_id', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'key', 'channel', 'locale'], 'notification_templates_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
