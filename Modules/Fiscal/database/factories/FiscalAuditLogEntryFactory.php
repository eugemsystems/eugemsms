<?php

declare(strict_types=1);

namespace Modules\Fiscal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalAuditLogEntry;

/**
 * @extends Factory<FiscalAuditLogEntry>
 */
class FiscalAuditLogEntryFactory extends Factory
{
    protected $model = FiscalAuditLogEntry::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'event_type' => 'ping',
            'occurred_at' => now(),
        ];
    }
}
