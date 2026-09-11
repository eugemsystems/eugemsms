<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Saas\Models\IncidentStatusEntry;

/**
 * @extends Factory<IncidentStatusEntry>
 */
class IncidentStatusEntryFactory extends Factory
{
    protected $model = IncidentStatusEntry::class;

    public function definition(): array
    {
        return [
            'title' => 'Elevated payment gateway latency',
            'affected_components' => ['payments'],
            'severity' => 'minor',
            'status' => 'investigating',
            'updates' => [],
            'is_public' => true,
        ];
    }
}
