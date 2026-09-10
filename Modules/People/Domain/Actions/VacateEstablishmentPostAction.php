<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\EstablishmentPost;

/**
 * ACT-VacateEstablishmentPost (Book C PPL-04 §2). The counterpart to
 * `FillEstablishmentPostAction` — called when a staff member leaves a
 * post (transfer, exit) so the vacancy is visible again.
 */
final class VacateEstablishmentPostAction extends Action
{
    public function execute(int $postId): EstablishmentPost
    {
        $post = EstablishmentPost::findOrFail($postId);

        return $this->transaction(function () use ($post): EstablishmentPost {
            if ($post->filled_count > 0) {
                $post->decrement('filled_count');
            }

            return $post->fresh();
        });
    }
}
