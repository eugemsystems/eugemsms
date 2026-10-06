<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\PublishContentItemData;
use Modules\Academic\Models\ContentItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;

final class PublishContentItemAction extends Action
{
    public function execute(PublishContentItemData $data): ContentItem
    {
        $item = ContentItem::findOrFail($data->contentItemId);

        // A file only reaches learners once its virus scan has come back clean (BR-ACA-08-011).
        if ($item->file_id !== null && ! File::query()->findOrFail($item->file_id)->hasCleanScan()) {
            throw new InvalidArgumentException('This file has not passed its virus scan yet, so it cannot be published.');
        }

        return $this->transaction(function () use ($item): ContentItem {
            $item->update(['published_at' => $item->published_at ?? Carbon::now()]);

            return $item->fresh();
        });
    }
}
