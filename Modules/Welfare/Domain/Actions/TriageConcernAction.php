<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * ACT-TriageConcern (Book G BRD-08 §2/BR-BRD-08-012/AC-BRD-08-012).
 * A rationale is mandatory even for "no further action" — a concern
 * closed without a reason is not closed, so this refuses an empty one
 * outright rather than allowing a silent dismissal.
 */
final class TriageConcernAction extends Action
{
    public function execute(int $concernId, string $triageStatus, string $rationale, int $triagedByUserId): SafeguardingConcern
    {
        if (trim($rationale) === '') {
            throw ValidationException::withMessages([
                'rationale' => 'A triage rationale is mandatory, including for no_further_action (BR-BRD-08-012).',
            ]);
        }

        $concern = SafeguardingConcern::findOrFail($concernId);

        return $this->transaction(fn (): SafeguardingConcern => tap($concern)->update([
            'triage_status' => $triageStatus,
            'triaged_by' => $triagedByUserId,
            'triaged_at' => Carbon::now(),
            'triage_rationale' => $rationale,
        ]));
    }
}
