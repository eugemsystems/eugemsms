<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\DiscountScheme;

/**
 * ACT-SetDiscountSchemeActive (Book K FIN-07 §5). Closing a scheme stops new
 * applications and grants; awards already made keep their terms and keep
 * billing — withdrawing a scheme never reaches back into what a learner was
 * promised.
 */
final class SetDiscountSchemeActiveAction extends Action
{
    public function execute(int $schemeId, bool $isActive): DiscountScheme
    {
        $scheme = DiscountScheme::query()->findOrFail($schemeId);

        return $this->transaction(function () use ($scheme, $isActive): DiscountScheme {
            $scheme->update(['is_active' => $isActive]);

            return $scheme->fresh();
        });
    }
}
