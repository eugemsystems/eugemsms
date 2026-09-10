<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\FillEstablishmentPostData;
use Modules\People\Domain\Exceptions\EstablishmentCapacityExceededException;
use Modules\People\Models\EstablishmentPost;

/**
 * ACT-FillEstablishmentPost (Book C PPL-04 §4/BR-PPL-04-004).
 */
final class FillEstablishmentPostAction extends Action
{
    public function execute(FillEstablishmentPostData $data): EstablishmentPost
    {
        $post = EstablishmentPost::findOrFail($data->postId);

        if ($post->filled_count >= $post->approved_count && ! $data->overrideEstablishment) {
            throw EstablishmentCapacityExceededException::forPost($post->id);
        }

        if ($data->overrideEstablishment && ($data->overrideReason === null || trim($data->overrideReason) === '')) {
            throw new InvalidArgumentException('overrideReason is required when overriding establishment capacity (BR-PPL-04-004).');
        }

        return $this->transaction(function () use ($post): EstablishmentPost {
            $post->increment('filled_count');

            return $post->fresh();
        });
    }
}
