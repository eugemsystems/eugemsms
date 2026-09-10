<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\PostNoticeData;
use Modules\Comms\Models\Notice;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-PostNotice (Book I COM-06 §2/BR-COM-06-003/004).
 */
final class PostNoticeAction extends Action
{
    public function execute(PostNoticeData $data): Notice
    {
        $publishAt = $data->publishAt ?? Carbon::now();

        return $this->transaction(fn (): Notice => Notice::create([
            'school_id' => $data->schoolId,
            'title' => $data->title,
            'body' => $data->body,
            'priority' => $data->priority,
            'audience_scope' => $data->audienceScope,
            'audience_scope_id' => $data->audienceScopeId,
            'is_pinned' => $data->isPinned,
            'publish_at' => $publishAt,
            'expires_at' => $data->expiresAt,
            'attachment_file_ids' => $data->attachmentFileIds,
            'posted_by' => $data->postedByUserId,
            'status' => $publishAt->lessThanOrEqualTo(Carbon::now()) ? 'published' : 'scheduled',
        ]));
    }
}
