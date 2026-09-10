<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RecordBreachNotificationDecisionData;
use Modules\Compliance\Models\DataBreach;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordBreachNotificationDecision (Book H3 CMP-03 §3/BR-CMP-03-
 * 012). The decision and its timing are recorded whether or not
 * notification actually happens — a deliberate "no" is as much a
 * recorded fact as a "yes".
 */
final class RecordBreachNotificationDecisionAction extends Action
{
    public function execute(RecordBreachNotificationDecisionData $data): DataBreach
    {
        return $this->transaction(function () use ($data): DataBreach {
            $breach = DataBreach::findOrFail($data->breachId);

            $breach->update([
                'authority_notified' => $data->authorityNotified,
                'authority_notified_at' => $data->authorityNotified ? Carbon::now() : null,
                'subjects_notified' => $data->subjectsNotified,
                'subjects_notified_at' => $data->subjectsNotified ? Carbon::now() : null,
                'status' => 'notified',
            ]);

            return $breach;
        });
    }
}
