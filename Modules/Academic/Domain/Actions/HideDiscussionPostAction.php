<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\HideDiscussionPostData;
use Modules\Academic\Models\DiscussionPost;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-HideDiscussionPost (Book K ACA-08 §4/BR-ACA-08-010). The post
 * itself is never deleted — only hidden from learners, with a reason
 * logged against the moderator.
 */
final class HideDiscussionPostAction extends Action
{
    public function execute(HideDiscussionPostData $data): DiscussionPost
    {
        if (trim($data->reason) === '') {
            throw new InvalidArgumentException('A reason is required to hide a discussion post.');
        }

        $post = DiscussionPost::findOrFail($data->postId);

        return $this->transaction(function () use ($post, $data): DiscussionPost {
            $post->update([
                'is_hidden' => true,
                'hidden_by' => $data->hiddenByUserId,
                'hidden_reason' => $data->reason,
                'hidden_at' => Carbon::now(),
            ]);

            return $post->fresh();
        });
    }
}
