<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Compliance\Domain\DataObjects\ConsolidatedIncidentRegisterEntry;
use Modules\Compliance\Models\DataBreach;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Models\OccurrenceBookEntry;
use Modules\Transport\Models\VehicleIncident;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\HealthIncident;

/**
 * ACT-GenerateConsolidatedIncidentRegister (Book H3 CMP-04 §3 ⭐/
 * BR-CMP-04-008 (AC-CMP-04-003)). Consolidates health, discipline,
 * security, transport and data-protection incidents for board
 * reporting — safeguarding (`Modules\Welfare\Models\SafeguardingCase`)
 * is EXCLUDED entirely; this action never queries it, matching
 * `CompileSubjectAccessResponseAction`'s own doctrine in CMP-03.
 * Only `HealthIncident` and `DataBreach` carry a real `severity`
 * column — `BehaviourRecord`, `OccurrenceBookEntry` and
 * `VehicleIncident` don't, so their entries report `severity: null`
 * rather than a fabricated value.
 */
final class GenerateConsolidatedIncidentRegisterAction extends Action
{
    /**
     * @return Collection<int, ConsolidatedIncidentRegisterEntry>
     */
    public function execute(int $schoolId, string $periodStart, string $periodEnd): Collection
    {
        $entries = new Collection;

        HealthIncident::where('school_id', $schoolId)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->get()
            ->each(fn (HealthIncident $incident) => $entries->push(new ConsolidatedIncidentRegisterEntry(
                'health', $incident->occurred_at, $incident->incident_type, $incident->description, $incident->severity,
            )));

        BehaviourRecord::where('school_id', $schoolId)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->get()
            ->each(fn (BehaviourRecord $record) => $entries->push(new ConsolidatedIncidentRegisterEntry(
                'discipline', $record->occurred_at, $record->polarity, $record->description, null,
            )));

        OccurrenceBookEntry::where('school_id', $schoolId)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->get()
            ->each(fn (OccurrenceBookEntry $entry) => $entries->push(new ConsolidatedIncidentRegisterEntry(
                'security', $entry->occurred_at, $entry->category, $entry->description, null,
            )));

        VehicleIncident::where('school_id', $schoolId)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->get()
            ->each(fn (VehicleIncident $incident) => $entries->push(new ConsolidatedIncidentRegisterEntry(
                'transport', $incident->occurred_at, $incident->incident_type, $incident->description, null,
            )));

        DataBreach::where('school_id', $schoolId)
            ->whereBetween('detected_at', [$periodStart, $periodEnd])
            ->get()
            ->each(fn (DataBreach $breach) => $entries->push(new ConsolidatedIncidentRegisterEntry(
                'data_protection', $breach->detected_at, $breach->breach_type, $breach->description, $breach->severity,
            )));

        return $entries->sortBy(fn (ConsolidatedIncidentRegisterEntry $entry) => $entry->occurredAt)->values();
    }
}
