<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateContentItemData;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateContentItem (Book K ACA-08 §2). Created as a draft —
 * `PublishContentItemAction` is the only way it becomes visible to
 * learners, via `published_at`.
 */
final class CreateContentItemAction extends Action
{
    public function execute(CreateContentItemData $data): ContentItem
    {
        $courseSpace = CourseSpace::findOrFail($data->courseSpaceId);

        return $this->transaction(fn (): ContentItem => ContentItem::create([
            'school_id' => $courseSpace->school_id,
            'course_space_id' => $courseSpace->id,
            'content_type' => $data->contentType,
            'title' => $data->title,
            'file_id' => $data->fileId,
            'external_url' => $data->externalUrl,
            'file_size_bytes' => $data->fileSizeBytes,
            'is_downloadable_offline' => $data->isDownloadableOffline,
            'view_count' => 0,
            'sort_order' => $data->sortOrder,
        ]));
    }
}
