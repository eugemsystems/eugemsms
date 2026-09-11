<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-08 §3 ⭐/BR-ACA-08-003. `content_item.is_downloadable_offline
 * = false` is deliberate for large content a school wants viewable but
 * not hoarding a low-end phone's storage — it streams on request and
 * is never queued into the offline cache, automatically or otherwise.
 */
class ContentNotDownloadableOfflineException extends DomainException
{
    public static function forContentItem(int $contentItemId): self
    {
        return new self(
            "Content item #{$contentItemId} is stream-only and cannot be queued for offline download.",
            ['content_item_id' => $contentItemId],
        );
    }

    public function errorCode(): string
    {
        return 'CONTENT_NOT_DOWNLOADABLE_OFFLINE';
    }
}
