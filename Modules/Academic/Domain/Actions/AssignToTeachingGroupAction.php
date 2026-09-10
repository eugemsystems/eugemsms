<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\AssignToTeachingGroupData;
use Modules\Academic\Domain\Events\TeachingGroupAssigned;
use Modules\Academic\Domain\Exceptions\TeachingGroupCapacityExceededException;
use Modules\Academic\Domain\Exceptions\TeachingGroupCapacityRequiresAcknowledgementException;
use Modules\Academic\Models\TeachingGroup;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;

/**
 * ACT-AssignToTeachingGroup (Book D ACA-02 §5/BR-ACA-02-012/013).
 * Capacity is "always warned" (BR-ACA-02-012): with
 * `academic.enforce_teaching_group_capacity` on, an over-capacity
 * assignment is refused; off, it still requires the same
 * warn-then-acknowledge step `EnrolSubjectAction` uses for rule
 * violations, never a silent overfill.
 *
 * A learner may hold exactly one membership per subject per term
 * (BR-ACA-02-013): assigning to a new group for the same subject/term
 * closes the prior membership and decrements that group's count —
 * a move, not an addition.
 */
final class AssignToTeachingGroupAction extends Action
{
    public function __construct(private readonly SettingResolver $settings) {}

    public function execute(AssignToTeachingGroupData $data): TeachingGroupMember
    {
        $student = Student::findOrFail($data->studentId);
        $group = TeachingGroup::findOrFail($data->teachingGroupId);
        $effectiveFrom = $data->effectiveFrom ?? Carbon::now();

        $this->assertCapacity($group, $data->acknowledgeCapacityWarning);

        return $this->transaction(function () use ($student, $group, $effectiveFrom): TeachingGroupMember {
            $existing = TeachingGroupMember::query()
                ->where('student_id', $student->id)
                ->whereNull('effective_to')
                ->whereHas('teachingGroup', fn ($q) => $q->where('subject_id', $group->subject_id)->where('term_id', $group->term_id))
                ->first();

            if ($existing !== null && $existing->teaching_group_id === $group->id) {
                return $existing;
            }

            if ($existing !== null) {
                $existing->update(['effective_to' => $effectiveFrom->toDateString()]);
                TeachingGroup::whereKey($existing->teaching_group_id)->decrement('current_count');
            }

            $member = TeachingGroupMember::create([
                'school_id' => $student->school_id,
                'teaching_group_id' => $group->id,
                'student_id' => $student->id,
                'effective_from' => $effectiveFrom->toDateString(),
            ]);

            $group->increment('current_count');

            event(new TeachingGroupAssigned($member));

            return $member;
        });
    }

    private function assertCapacity(TeachingGroup $group, bool $acknowledged): void
    {
        if ($group->capacity === null || $group->hasCapacityFor()) {
            return;
        }

        $enforced = (bool) $this->settings->get('academic.enforce_teaching_group_capacity', new ScopeChain(schoolId: $group->school_id));

        if ($enforced) {
            throw TeachingGroupCapacityExceededException::forGroup($group->code, $group->capacity, $group->current_count);
        }

        if (! $acknowledged) {
            throw TeachingGroupCapacityRequiresAcknowledgementException::forGroup($group->code, $group->capacity, $group->current_count);
        }
    }
}
