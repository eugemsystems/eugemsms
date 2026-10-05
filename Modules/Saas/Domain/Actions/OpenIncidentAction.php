<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\OpenIncidentData;
use Modules\Saas\Models\IncidentStatusEntry;

/**
 * ACT-OpenIncident (Book J SAA-02 §2/§4).
 */
final class OpenIncidentAction extends Action
{
    public function execute(OpenIncidentData $data): IncidentStatusEntry
    {
        if (trim($data->title) === '' || mb_strlen($data->title) > 200 || trim($data->initialMessage) === '' || mb_strlen($data->initialMessage) > 2000) {
            throw new InvalidArgumentException('An incident needs a title and an initial message.');
        }

        if (! in_array($data->severity, ['minor', 'major', 'critical'], true)) {
            throw new InvalidArgumentException("[{$data->severity}] is not an incident severity.");
        }

        if ($data->affectedComponents === []) {
            throw new InvalidArgumentException('Name at least one affected component.');
        }

        return $this->transaction(fn (): IncidentStatusEntry => IncidentStatusEntry::create([
            'title' => $data->title,
            'affected_components' => $data->affectedComponents,
            'severity' => $data->severity,
            'status' => 'investigating',
            'updates' => [
                ['at' => Carbon::now()->toIso8601String(), 'message' => $data->initialMessage, 'status' => 'investigating'],
            ],
            'is_public' => $data->isPublic,
        ]));
    }
}
