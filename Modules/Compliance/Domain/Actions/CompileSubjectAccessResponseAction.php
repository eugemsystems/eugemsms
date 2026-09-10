<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\Exceptions\IdentityNotVerifiedException;
use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CalculateSubledgerBalanceAction;
use Modules\Finance\Domain\DataObjects\CalculateSubledgerBalanceData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-CompileSubjectAccessResponse (Book H3 CMP-03 §3 ⭐/BR-CMP-03-008/
 * 009 (AC-CMP-03-002/003)). Refuses outright if identity isn't
 * verified yet (BR-CMP-03-008's own literal ordering). Compiles ONLY
 * from data this action can honestly assert is the subject's own,
 * non-safeguarding data: `Student` bio-data, linked guardians'
 * relationship (never the guardian's own separate personal data —
 * BR-CMP-03-009's "third-party personal data" exclusion), and the
 * real GL-sourced balance via `CalculateSubledgerBalanceAction`
 * (Book B FIN-01's own single source of truth). Safeguarding and
 * medical records are excluded entirely, not filtered post-hoc — this
 * action never queries `Modules\Welfare` at all.
 */
final class CompileSubjectAccessResponseAction extends Action
{
    public function __construct(
        private readonly CalculateSubledgerBalanceAction $calculateSubledgerBalance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $requestId): array
    {
        $request = SubjectAccessRequest::findOrFail($requestId);

        if (! $request->identity_verified) {
            throw IdentityNotVerifiedException::forRequest($request->id);
        }

        if ($request->subject_type !== 'student' || $request->subject_id === null) {
            throw new InvalidStateTransitionException(
                "Subject access request #{$request->id} has no compilable student subject.",
                ['request_id' => $request->id, 'subject_type' => $request->subject_type],
            );
        }

        $student = Student::findOrFail($request->subject_id);
        $school = School::findOrFail($request->school_id);

        $balance = $this->calculateSubledgerBalance->execute(new CalculateSubledgerBalanceData(
            schoolId: $request->school_id,
            subledgerType: 'student',
            subledgerId: $student->id,
            currency: $school->base_currency,
            asAt: Carbon::now(),
        ));

        $guardianRelationships = StudentGuardian::where('student_id', $student->id)
            ->get()
            ->map(fn (StudentGuardian $link): array => ['relationship' => $link->relationship, 'is_primary_contact' => $link->is_primary_contact])
            ->values()
            ->all();

        $compiled = [
            'bio_data' => [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'date_of_birth' => $student->date_of_birth->toDateString(),
                'gender' => $student->gender,
                'admission_number' => $student->admission_number,
                'status' => $student->status,
            ],
            'guardian_relationships' => $guardianRelationships,
            'financial_balance_minor' => $balance->minor,
            'financial_currency' => $balance->currency->value,
            'excluded' => ['safeguarding_records', 'medical_records', 'third_party_personal_data'],
        ];

        return $this->transaction(function () use ($request, $compiled): array {
            $request->update(['status' => 'under_review']);

            return $compiled;
        });
    }
}
