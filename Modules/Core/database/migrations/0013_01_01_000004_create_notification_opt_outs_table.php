<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_opt_outs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->string('address', 200);
            $table->string('channel', 20);
            $table->string('reason', 120)->nullable();
            $table->timestamp('opted_out_at');

            $table->unique(['school_id', 'address', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_opt_outs');
    }
};
