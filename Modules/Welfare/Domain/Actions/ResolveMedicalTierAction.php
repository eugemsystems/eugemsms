<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordDataAccessAction;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\Support\CareResponsibilityResolver;
use Modules\Welfare\Domain\Support\MedicalTier;

/**
 * ACT-ResolveMedicalTier (Book G BRD-06 §3 ⭐/BR-BRD-06-001/002/
 * AC-BRD-06-001/002). Mirrors the spec's own `MedicalConditionPolicy`
 * almost verbatim. Tier is resolved, not merely checked — the caller
 * gets back what it is entitled to see, never a plain yes/no, so a
 * Tier 2 caller can never accidentally receive Tier 3 fields. Every
 * Tier 3 resolution writes to `data_access_log` (`CORE-08`) — reads,
 * not just writes.
 */
final class ResolveMedicalTierAction extends Action
{
    public function __construct(
        private readonly CareResponsibilityResolver $careResponsibility,
        private readonly RecordDataAccessAction $recordDataAccess,
    ) {}

    public function execute(User $user, Student $student, ?string $ip = null): MedicalTier
    {
        if ($user->hasPermissionTo('health.clinical.view')) {
            $this->recordDataAccess->execute(new RecordDataAccessData(
                schoolId: $student->school_id,
                userId: $user->id,
                accessType: 'view',
                resourceType: 'clinical_record',
                resourceId: $student->id,
                ip: $ip,
            ));

            return MedicalTier::Clinical;
        }

        if ($user->hasPermissionTo('health.actionable.view') && $this->careResponsibility->currentlyResponsibleFor($user, $student)) {
            return MedicalTier::Actionable;
        }

        if ($user->hasPermissionTo('health.actionable.view') || $user->can('view', $student)) {
            return MedicalTier::Existence;
        }

        throw new InsufficientScopeException("User #{$user->id} has no visibility of student #{$student->id}'s medical data.");
    }

    /**
     * BR-BRD-06-003 — guardians with an active, unrestricted
     * relationship see their own child's full record.
     */
    public function forGuardian(Guardian $guardian, Student $student): MedicalTier
    {
        $link = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->where('status', 'active')
            ->first();

        if ($link === null || $link->has_court_restriction) {
            throw new InsufficientScopeException("Guardian #{$guardian->id} has no active, unrestricted relationship to student #{$student->id}.");
        }

        return MedicalTier::Clinical;
    }
}
