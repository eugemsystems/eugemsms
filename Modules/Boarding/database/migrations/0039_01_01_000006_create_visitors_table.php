<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book F BRD-03 §2/BR-BRD-03-017/018/019. `id_number` is ENCRYPTED at
 * rest via the model's own cast. `photo_file_id` is a forward
 * reference to `CORE-10`, no FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('id_type', 30)->nullable();
            $table->text('id_number')->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->string('organisation', 150)->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->foreignId('blacklisted_by')->nullable()->constrained('users');
            $table->boolean('is_watchlisted')->default(false);
            $table->text('watchlist_note')->nullable();
            $table->foreignId('linked_guardian_id')->nullable()->constrained('guardians');
            $table->timestamps();

            $table->index(['school_id', 'full_name'], 'visitors_name_idx');
            $table->index(['school_id', 'is_blacklisted'], 'visitors_blacklist_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
