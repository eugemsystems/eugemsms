<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
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
