<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\PublishContentItemData;
use Modules\Academic\Models\ContentItem;
use Modules\Core\Domain\Actions\Action;

final class PublishContentItemAction extends Action
{
    public function execute(PublishContentItemData $data): ContentItem
    {
        $item = ContentItem::findOrFail($data->contentItemId);

        return $this->transaction(function () use ($item): ContentItem {
            $item->update(['published_at' => $item->published_at ?? Carbon::now()]);

            return $item->fresh();
        });
    }
}
