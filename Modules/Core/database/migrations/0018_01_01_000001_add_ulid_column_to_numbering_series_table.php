<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 2026-09-12, user-reported: editing a numbering series showed
 * `/numbering/2/edit` (a sequential id, then 404s once route binding
 * requires ulid — see `Modules\Core\Domain\Concerns\HasUlid`'s
 * docblock). The spec's own `numbering_series` data model (Book A
 * CORE-06 §2) never listed a `ulid` column, unlike every other table
 * this same module added (`document_templates`, `documents`,
 * `document_batches`) — an inconsistency in the spec itself, not a
 * deliberate exception; every table with a "show"/"edit" URL needs an
 * unguessable identifier per BR-GLOBAL-040. Nullable and backfilled by
 * a loop rather than a DB-generated default, matching every other
 * `HasUlid` rollout in this project (no doctrine/dbal dependency to
 * `->change()` it NOT NULL afterward).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_series', function (Blueprint $table): void {
            $table->char('ulid', 26)->nullable()->unique()->after('id');
        });

        DB::table('numbering_series')->whereNull('ulid')->orderBy('id')->get(['id'])->each(function (object $row): void {
            DB::table('numbering_series')->where('id', $row->id)->update(['ulid' => (string) Str::ulid()]);
        });
    }

    public function down(): void
    {
        Schema::table('numbering_series', function (Blueprint $table): void {
            $table->dropColumn('ulid');
        });
    }
};
