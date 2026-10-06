<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RemoveHouseholdMemberData;
use Modules\People\Models\HouseholdMember;

/**
 * ACT-RemoveHouseholdMember (Book C PPL-03 §3/BR-PPL-03-016). Membership is
 * dated: leaving sets `left_on`; the row stays as history.
 */
final class RemoveHouseholdMemberAction extends Action
{
    public function execute(RemoveHouseholdMemberData $data): HouseholdMember
    {
        $member = HouseholdMember::query()->where('household_id', $data->householdId)->where('member_type', $data->memberType)->where('member_id', $data->memberId)->whereNull('left_on')->first();

        if ($member === null) {
            throw new InvalidArgumentException('That member is not currently in this household.');
        }

        $leftOn = ($data->leftOn ?? Carbon::today());

        if ($leftOn->lt($member->joined_on)) {
            throw new InvalidArgumentException('A member cannot leave before they joined.');
        }

        return $this->transaction(function () use ($member, $leftOn): HouseholdMember {
            $member->update(['left_on' => $leftOn->toDateString()]);

            return $member->fresh();
        });
    }
}
