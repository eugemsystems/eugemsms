<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\RecordContentDownloadData;
use Modules\Academic\Domain\Exceptions\ContentNotDownloadableOfflineException;
use Modules\Academic\Models\ContentDownloadCache;
use Modules\Academic\Models\ContentItem;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordContentDownload (Book K ACA-08 §3 ⭐/BR-ACA-08-003).
 * Refuses to queue a stream-only item (`is_downloadable_offline =
 * false`) into the offline cache at all — that flag exists precisely
 * to keep large content out of a low-end phone's storage.
 */
final class RecordContentDownloadAction extends Action
{
    public function execute(RecordContentDownloadData $data): ContentDownloadCache
    {
        $item = ContentItem::findOrFail($data->contentItemId);

        if (! $item->is_downloadable_offline) {
            throw ContentNotDownloadableOfflineException::forContentItem($item->id);
        }

        return $this->transaction(fn (): ContentDownloadCache => ContentDownloadCache::updateOrCreate(
            ['user_id' => $data->userId, 'content_item_id' => $item->id, 'device_id' => $data->deviceId],
            ['downloaded_at' => Carbon::now()],
        ));
    }
}
