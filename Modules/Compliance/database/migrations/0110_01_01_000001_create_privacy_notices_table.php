<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Book H3 CMP-03 §2/§3 (BR-CMP-03-002/015). **Addition to the
 * specification**: the spec's own data model omits a `privacy_notices`
 * table despite `consents.notice_version` needing a real source of
 * truth for "what version was in force" and the "Privacy notices"
 * screen it names. Added here, minimally, following the closest
 * existing pattern (a plain versioned, append-style record — mirrors
 * `zimsec_validation_rules`' own school-scoped-or-system-default
 * shape). `requires_reconsent` is what BR-CMP-03-015 acts on: a
 * material change sets it true on the new version, and every
 * consent-based `consent_type` needs fresh consent against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->string('title', 200);
            $table->longText('content');
            $table->date('effective_from');
            $table->boolean('requires_reconsent')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['school_id', 'version'], 'privacy_notices_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_notices');
    }
};
