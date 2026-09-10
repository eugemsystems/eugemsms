<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Security\Models\LostProperty;

/**
 * ACT-ClaimLostProperty (Book H2 OPS-06 §2).
 */
final class ClaimLostPropertyAction extends Action
{
    public function execute(int $lostPropertyId, int $claimedByStudentId): LostProperty
    {
        $item = LostProperty::findOrFail($lostPropertyId);

        if ($item->status !== 'held') {
            throw new InvalidStateTransitionException(
                "Lost property #{$item->id} must be held to claim (currently {$item->status}).",
                ['lost_property_id' => $item->id, 'status' => $item->status],
            );
        }

        return $this->transaction(fn (): LostProperty => tap($item)->update([
            'status' => 'claimed',
            'claimed_by_student_id' => $claimedByStudentId,
            'claimed_at' => now(),
        ]));
    }
}
