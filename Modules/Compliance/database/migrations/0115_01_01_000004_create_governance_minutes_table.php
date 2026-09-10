<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-007. `access_role_ids` is what a
 * `confidential`/`restricted` minute's access-logging check reads
 * against — no role id present means no one may view it, per
 * BR-CMP-04-007's "restricted to named roles" wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_minutes', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('body', 40);
            $table->date('meeting_date');
            $table->json('attendees');
            $table->unsignedBigInteger('minutes_file_id')->nullable();
            $table->json('resolutions')->nullable();
            $table->string('confidentiality', 20);
            $table->json('access_role_ids')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'body', 'meeting_date'], 'governance_minutes_meeting_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_minutes');
    }
};
