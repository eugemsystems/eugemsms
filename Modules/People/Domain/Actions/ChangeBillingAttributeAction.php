<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Domain\DataObjects\ChangeBillingAttributeData;
use Modules\People\Domain\Events\LearnerBillingAttributeChanged;
use Modules\People\Domain\Events\LearnerClassChanged;
use Modules\People\Domain\Events\LearnerEnrolmentTypeChanged;
use Modules\People\Domain\Events\LearnerGradeLevelChanged;
use Modules\People\Domain\Events\LearnerPathwayChanged;
use Modules\People\Domain\Events\LearnerResidencyChanged;
use Modules\People\Domain\Events\LearnerSectionChanged;
use Modules\People\Domain\Exceptions\BackdateLimitExceededException;
use Modules\People\Domain\Exceptions\InvalidBillingAttributeException;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;
use RuntimeException;

/**
 * ACT-ChangeBillingAttribute (Book C PPL-01 §3/§5 ⭐/BR-PPL-01-004..006).
 * The single path for `enrolment_type`, `residency`, `grade_level`,
 * `class`, `section`, and `pathway`. Writes the append-only history row
 * *and* updates `students`' current-state column in the same
 * transaction, then emits the attribute-specific event — `FIN-02`
 * reads the history for proration, everything else reads the current
 * column.
 */
final class ChangeBillingAttributeAction extends Action
{
    /**
     * attribute key => [students column, event class, triggers_rebilling default].
     *
     * @var array<string, array{0: string, 1: class-string<LearnerBillingAttributeChanged>, 2: bool}>
     */
    private const array ATTRIBUTE_MAP = [
        'enrolment_type' => ['enrolment_type', LearnerEnrolmentTypeChanged::class, true],
        'residency' => ['residency', LearnerResidencyChanged::class, true],
        'grade_level' => ['grade_level_id', LearnerGradeLevelChanged::class, true],
        'class' => ['class_id', LearnerClassChanged::class, false],
        'section' => ['section_id', LearnerSectionChanged::class, true],
        'pathway' => ['pathway', LearnerPathwayChanged::class, true],
    ];

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ChangeBillingAttributeData $data): StudentAttributeChange
    {
        if (! array_key_exists($data->attribute, self::ATTRIBUTE_MAP)) {
            throw InvalidBillingAttributeException::forAttribute($data->attribute);
        }

        [$column, $eventClass, $triggersRebilling] = self::ATTRIBUTE_MAP[$data->attribute];

        $student = Student::findOrFail($data->studentId);

        $this->assertBackdateAllowed($student->school_id, $data->effectiveFrom);

        $oldValue = $student->getAttribute($column) !== null ? (string) $student->getAttribute($column) : null;

        return $this->transaction(function () use ($student, $data, $column, $eventClass, $triggersRebilling, $oldValue): StudentAttributeChange {
            $change = StudentAttributeChange::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'academic_year_id' => $this->currentAcademicYearId($student),
                'term_id' => $this->currentTermId($student),
                'attribute' => $data->attribute,
                'old_value' => $oldValue,
                'new_value' => $data->newValue,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'reason_code' => $data->reasonCode,
                'reason' => $data->reason,
                'triggers_rebilling' => $triggersRebilling,
                'rebilling_status' => $triggersRebilling ? 'pending' : 'not_required',
                'changed_by' => $data->changedByUserId,
                'changed_at' => Carbon::now(),
            ]);

            $student->billingAttributeChangeAuthorized = true;
            $student->update(['updated_by' => $data->changedByUserId, $column => $data->newValue]);

            event(new $eventClass($student, $change));

            return $change;
        });
    }

    private function assertBackdateAllowed(int $schoolId, CarbonInterface $effectiveFrom): void
    {
        $daysInPast = (int) Carbon::now()->startOfDay()->diffInDays($effectiveFrom->copy()->startOfDay(), absolute: true);

        if ($effectiveFrom->startOfDay()->greaterThanOrEqualTo(Carbon::now()->startOfDay())) {
            return;
        }

        $allowBackdating = (bool) $this->settings->get('students.allow_backdated_attribute_change', new ScopeChain(schoolId: $schoolId));

        if (! $allowBackdating) {
            throw BackdateLimitExceededException::backdatingDisabled();
        }

        $limitDays = (int) $this->settings->get('students.backdate_limit_days', new ScopeChain(schoolId: $schoolId));

        if ($daysInPast > $limitDays) {
            throw BackdateLimitExceededException::forDays($daysInPast, $limitDays);
        }
    }

    private function currentAcademicYearId(Student $student): int
    {
        return $student->enrolments()->latest('id')->value('academic_year_id')
            ?? throw new RuntimeException("Student [{$student->id}] has no enrolment to attribute this change to.");
    }

    private function currentTermId(Student $student): int
    {
        return $student->enrolments()->latest('id')->value('term_id')
            ?? throw new RuntimeException("Student [{$student->id}] has no enrolment to attribute this change to.");
    }
}
