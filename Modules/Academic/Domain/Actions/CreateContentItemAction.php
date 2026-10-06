<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateContentItemData;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;

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

        if (! in_array($data->contentType, ['note', 'worksheet', 'past_paper', 'audio', 'video', 'link', 'folder'], true)) {
            throw new InvalidArgumentException("[{$data->contentType}] is not a content type.");
        }

        if (trim($data->title) === '' || mb_strlen($data->title) > 200) {
            throw new InvalidArgumentException('Content needs a title of up to 200 characters.');
        }

        if ($data->contentType !== 'folder' && $data->fileId === null && $data->externalUrl === null) {
            throw new InvalidArgumentException('Content needs a file or a link.');
        }

        // The link is rendered as an anchor for learners, so only http(s) is acceptable —
        // never `javascript:` or `data:` URLs.
        if ($data->externalUrl !== null && (mb_strlen($data->externalUrl) > 500 || preg_match('#^https?://[^\s]+$#i', $data->externalUrl) !== 1)) {
            throw new InvalidArgumentException('A content link must be an http or https address.');
        }

        $fileSizeBytes = $data->fileSizeBytes;

        if ($data->fileId !== null) {
            $file = File::query()->where('school_id', $courseSpace->school_id)->findOrFail($data->fileId);

            if ($file->isInfected()) {
                throw new InvalidArgumentException('That file failed its virus scan and cannot be used.');
            }

            // The size learners see before downloading comes from the stored file, not the caller.
            $fileSizeBytes = (int) $file->size_bytes;
        }

        return $this->transaction(fn (): ContentItem => ContentItem::create([
            'school_id' => $courseSpace->school_id,
            'course_space_id' => $courseSpace->id,
            'content_type' => $data->contentType,
            'title' => $data->title,
            'file_id' => $data->fileId,
            'external_url' => $data->externalUrl,
            'file_size_bytes' => $fileSizeBytes,
            'is_downloadable_offline' => $data->isDownloadableOffline,
            'view_count' => 0,
            'sort_order' => $data->sortOrder,
        ]));
    }
}
