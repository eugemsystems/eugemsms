<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Book B FIN-06 §2/§6. The spec's own screen list has no "manage rate
 * sources" screen — a school captures rates against a source, it
 * doesn't create sources — so usable, school-agnostic sources must
 * exist out of the box or `Finance\Currency\CaptureRate` would have
 * nothing to select. Two, matching the two workflows `exchange_rates`'
 * own `status` column and `Finance\Currency\ApproveRate` exist to
 * support: a manually entered rate that takes effect immediately, and
 * one that lands `pending` until a second person reviews it
 * (BR-FIN-06-005). `key`/`priority` already support adding a real
 * automatic feed (RBZ interbank, a gateway's own rate) later without
 * changing shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('exchange_rate_sources')->insert([
            [
                'school_id' => null,
                'key' => 'school_rate',
                'name' => 'Manually entered school rate',
                'is_automatic' => false,
                'endpoint_url' => null,
                'requires_approval' => false,
                'priority' => 0,
                'is_active' => true,
            ],
            [
                'school_id' => null,
                'key' => 'school_rate_reviewed',
                'name' => 'Manually entered rate (requires approval)',
                'is_automatic' => false,
                'endpoint_url' => null,
                'requires_approval' => true,
                'priority' => 10,
                'is_active' => true,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('exchange_rate_sources')
            ->whereIn('key', ['school_rate', 'school_rate_reviewed'])
            ->whereNull('school_id')
            ->delete();
    }
};
