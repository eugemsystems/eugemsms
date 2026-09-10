<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Audit;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\Events\Audit\SecurityEventRaised;
use Modules\Core\Models\SecurityEvent;

final class RecordSecurityEventAction extends Action
{
    public function execute(RecordSecurityEventData $data): SecurityEvent
    {
        return $this->transaction(function () use ($data): SecurityEvent {
            $event = SecurityEvent::create([
                'school_id' => $data->schoolId,
                'event_type' => $data->eventType,
                'severity' => $data->severity,
                'user_id' => $data->userId,
                'description' => $data->description,
                'context' => $data->context,
                'ip_address' => $data->ip,
                'user_agent' => $data->userAgent,
                'is_reviewed' => false,
                'occurred_at' => now(),
            ]);

            event(new SecurityEventRaised($event));

            return $event;
        });
    }
}
