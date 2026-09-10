<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Audit;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Audit\ReviewSecurityEventData;
use Modules\Core\Models\SecurityEvent;

final class ReviewSecurityEventAction extends Action
{
    public function execute(ReviewSecurityEventData $data): SecurityEvent
    {
        $event = SecurityEvent::query()->findOrFail($data->securityEventId);

        return $this->transaction(function () use ($event, $data): SecurityEvent {
            $event->forceFill([
                'is_reviewed' => true,
                'reviewed_by' => $data->reviewedByUserId,
                'reviewed_at' => Carbon::now(),
                'review_notes' => $data->notes,
            ])->save();

            return $event;
        });
    }
}
