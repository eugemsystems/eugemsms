<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Modules\Boarding\Domain\DataObjects\CollectionClaim;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\Visitor;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book F BRD-03 §3 ⭐ — "the single most important twenty lines in
 * this book." Runs on every departure with no fast path and no
 * trusted-parent bypass (BR-BRD-03-011). A court restriction
 * overrides every other right regardless of its own value
 * (BR-PPL-03-011, already encoded in `StudentGuardian::canCollectLearner()`
 * — re-checked explicitly here too so the refusal reason is specific
 * rather than a generic "no right").
 */
final class CollectionAuthorityChecker
{
    public function check(Student $student, CollectionClaim $claim, ?Exeat $exeat, bool $requiresPhotoId = true): CollectionDecision
    {
        if ($exeat === null || ! $exeat->isApprovedAndCurrent()) {
            return $this->refuse('no_exeat', escalate: true);
        }

        if ($this->matchesBlacklistedVisitor($student->school_id, $claim)) {
            return $this->refuse('blacklisted', escalate: true, alertSecurity: true);
        }

        $authorised = $exeat->collecting_guardian_id !== null
            ? $claim->guardianId === $exeat->collecting_guardian_id
            : $this->matchesOneOffPerson($claim, $exeat);

        if (! $authorised) {
            return $this->refuse('no_right', escalate: true);
        }

        $relationship = $claim->guardianId !== null
            ? StudentGuardian::query()->where('student_id', $student->id)->where('guardian_id', $claim->guardianId)->where('status', 'active')->first()
            : null;

        if ($relationship?->has_court_restriction) {
            return $this->refuse('court_restriction', escalate: true, alertHead: true);
        }

        if ($claim->guardianId !== null && ($relationship === null || ! $relationship->may_collect_learner)) {
            return $this->refuse('no_right', escalate: true);
        }

        if ($requiresPhotoId && ! $claim->identityVerified) {
            return $this->refuse('identity_unverified', escalate: false);
        }

        return new CollectionDecision(released: true);
    }

    private function matchesOneOffPerson(CollectionClaim $claim, Exeat $exeat): bool
    {
        if ($exeat->collecting_person_name === null || $exeat->one_off_authorisation_by === null) {
            return false;
        }

        return mb_strtolower(mb_trim($claim->name)) === mb_strtolower(mb_trim($exeat->collecting_person_name));
    }

    private function matchesBlacklistedVisitor(int $schoolId, CollectionClaim $claim): bool
    {
        return Visitor::query()
            ->where('school_id', $schoolId)
            ->where('is_blacklisted', true)
            ->get()
            ->contains(fn (Visitor $visitor): bool => mb_strtolower(mb_trim($visitor->full_name)) === mb_strtolower(mb_trim($claim->name))
                || ($claim->idNo !== null && $visitor->id_number === $claim->idNo));
    }

    private function refuse(string $reason, bool $escalate = false, bool $alertSecurity = false, bool $alertHead = false): CollectionDecision
    {
        return new CollectionDecision(released: false, refusalReason: $reason, escalate: $escalate, alertSecurity: $alertSecurity, alertHead: $alertHead);
    }
}
