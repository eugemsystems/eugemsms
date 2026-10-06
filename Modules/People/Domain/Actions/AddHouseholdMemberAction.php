<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\AddHouseholdMemberData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Household;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Student;

/**
 * ACT-AddHouseholdMember (Book C PPL-03 §3/BR-PPL-03-016). Puts a learner or a
 * guardian in a household from a date. A learner can be in only one household
 * at a time; one who left and returns has their old row reopened.
 */
final class AddHouseholdMemberAction extends Action
{
    public function execute(AddHouseholdMemberData $data): HouseholdMember
    {
        $household = Household::findOrFail($data->householdId);

        if (! in_array($data->memberType, ['student', 'guardian'], true)) {
            throw new InvalidArgumentException("Unknown member type [{$data->memberType}].");
        }

        $exists = $data->memberType === 'student'
            ? Student::query()->whereKey($data->memberId)->exists()
            : Guardian::query()->whereKey($data->memberId)->exists();

        if (! $exists) {
            throw new InvalidArgumentException('That member does not belong to this school.');
        }

        $joinedOn = ($data->joinedOn ?? Carbon::today())->toDateString();

        if ($data->memberType === 'student' && HouseholdMember::query()->where('member_type', 'student')->where('member_id', $data->memberId)->whereNull('left_on')->where('household_id', '!=', $household->id)->exists()) {
            throw new InvalidArgumentException('That learner is already in another household; remove them from it first.');
        }

        return $this->transaction(function () use ($household, $data, $joinedOn): HouseholdMember {
            $existing = HouseholdMember::query()->where('household_id', $household->id)->where('member_type', $data->memberType)->where('member_id', $data->memberId)->first();

            if ($existing !== null && $existing->left_on === null) {
                throw new InvalidArgumentException('That member is already in this household.');
            }

            if ($existing !== null) {
                $existing->update(['joined_on' => $joinedOn, 'left_on' => null]);

                return $existing->fresh();
            }

            return HouseholdMember::create([
                'school_id' => $household->school_id,
                'household_id' => $household->id,
                'member_type' => $data->memberType,
                'member_id' => $data->memberId,
                'joined_on' => $joinedOn,
            ]);
        });
    }
}
