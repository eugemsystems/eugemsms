<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book I COM-06 §2/BR-COM-06-004. A read receipt, once written, never
 * changes — no structural precedent for this exact shape exists
 * elsewhere in the codebase (checked), so this mirrors the closest
 * one: `ad_hoc_charges`/`invoice_lines`' `MUTABLE_AFTER_CREATE`
 * allow-list pattern, narrowed to an EMPTY list (see
 * `Modules\Comms\Models\NoticeRead::booted()`) since nothing about a
 * read receipt is ever legitimately revised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('read_at');

            $table->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
    }
};
