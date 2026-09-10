<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Exceptions\TimeLockedResourceNotSyncableException;
use Modules\Comms\Models\OfflineSyncManifest;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RequestOfflineSync (Book I COM-03 §4 ⭐/BR-COM-03-010
 * (AC-COM-03-005)). `'exam_papers'` refuses outright, structurally —
 * this is the ONE enforcement point every offline sync request passes
 * through, so no other code path can accidentally register a manifest
 * for it. Every other category defers entirely to its OWNING module's
 * own access rules at actual sync time — this action only records
 * that a manifest exists, never grants access itself (BR-COM-03-010's
 * "sync is a caching strategy, not a second authorisation layer").
 */
final class RequestOfflineSyncAction extends Action
{
    private const string FORBIDDEN_CATEGORY = 'exam_papers';

    public function execute(int $userId, string $dataCategory, int $cacheTtlHours = 24): OfflineSyncManifest
    {
        if ($dataCategory === self::FORBIDDEN_CATEGORY) {
            throw TimeLockedResourceNotSyncableException::forCategory($dataCategory);
        }

        return $this->transaction(fn (): OfflineSyncManifest => OfflineSyncManifest::updateOrCreate(
            ['user_id' => $userId, 'data_category' => $dataCategory],
            ['cache_ttl_hours' => $cacheTtlHours, 'last_synced_at' => Carbon::now()],
        ));
    }
}
