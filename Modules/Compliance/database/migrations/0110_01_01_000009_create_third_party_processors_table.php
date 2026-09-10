<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-014.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_party_processors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('processor_type', 40);
            $table->json('data_shared');
            $table->text('purpose');
            $table->char('country', 2)->nullable();
            $table->unsignedBigInteger('agreement_file_id')->nullable();
            $table->date('agreement_expires_on')->nullable();
            $table->string('status', 20);
            $table->date('last_reviewed_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_processors');
    }
};
