<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Audit;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\DataAccessLogEntry;

/**
 * ACT-RecordDataAccess (Book A CORE-08 BR-CORE-08-009/010/AC-CORE-08-004/005).
 * An export whose `recordCount` exceeds `audit.bulk_export_threshold`
 * also raises a security event — the caller doesn't need to check the
 * threshold itself.
 */
final class RecordDataAccessAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(RecordDataAccessData $data): DataAccessLogEntry
    {
        return $this->transaction(function () use ($data): DataAccessLogEntry {
            $entry = DataAccessLogEntry::create([
                'school_id' => $data->schoolId,
                'user_id' => $data->userId,
                'access_type' => $data->accessType,
                'resource_type' => $data->resourceType,
                'resource_id' => $data->resourceId,
                'record_count' => $data->recordCount,
                'purpose' => $data->purpose,
                'ip_address' => $data->ip,
                'accessed_at' => now(),
            ]);

            $threshold = (int) $this->settings->get('audit.bulk_export_threshold', new ScopeChain(schoolId: $data->schoolId));

            if ($data->recordCount !== null && $data->recordCount > $threshold) {
                $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                    eventType: 'bulk_export',
                    severity: 'warning',
                    description: "{$data->recordCount} {$data->resourceType} records exported, exceeding the {$threshold}-row threshold.",
                    schoolId: $data->schoolId,
                    userId: $data->userId,
                    context: ['resource_type' => $data->resourceType, 'record_count' => $data->recordCount],
                    ip: $data->ip,
                ));
            }

            return $entry;
        });
    }
}
