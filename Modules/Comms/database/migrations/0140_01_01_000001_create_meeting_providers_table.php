<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-07 §2. `credentials`/`webhook_secret` are `'encrypted'`
 * casts on the model — the same convention already established on
 * `Modules\Comms\Models\MessageGateway` (COM-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->text('credentials');
            $table->string('account_email', 150)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_providers');
    }
};
