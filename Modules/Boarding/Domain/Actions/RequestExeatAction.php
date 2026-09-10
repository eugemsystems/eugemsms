<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use InvalidArgumentException;
use Modules\Boarding\Domain\DataObjects\RequestExeatData;
use Modules\Boarding\Domain\Events\ExeatRequested;
use Modules\Boarding\Domain\Exceptions\ExeatBlockedException;
use Modules\Boarding\Domain\Exceptions\ExeatQuotaExceededException;
use Modules\Boarding\Domain\Exceptions\OneOffAuthorisationRequiredException;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatQuota;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-RequestExeat (Book F BRD-03 §4/§5/BR-BRD-03-001/003/005/006/
 * 007/014). Guardian-initiated types cannot be requested by staff on
 * a guardian's behalf, except via `request_source = phone_recorded`
 * with the recording staff member's own identity captured
 * (BR-BRD-03-001). Province/duration escalation (BR-BRD-03-003) and
 * short-notice flagging (BR-BRD-03-004) are recorded for the approval
 * screen to surface — this codebase has no multi-stage `CORE-07`
 * chain wired anywhere yet, so approval itself stays the single
 * decisive step `ApproveExeatAction` already is, matching
 * `AmendMarkAction`'s own documented boundary.
 */
final class RequestExeatAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(RequestExeatData $data): Exeat
    {
        $exeatType = ExeatType::findOrFail($data->exeatTypeId);

        if ($exeatType->requires_guardian_request
            && $data->requestedByGuardianId === null
            && ! ($data->requestSource === 'phone_recorded' && $data->requestedByUserId !== null)) {
            throw new InvalidArgumentException(
                'This exeat type requires guardian initiation — staff may only record it via request_source=phone_recorded with their own identity captured (BR-BRD-03-001).',
            );
        }

        if ($exeatType->blocks_on_fee_arrears && $data->feeArrearsExceedThreshold) {
            throw ExeatBlockedException::forReason($data->studentId, 'fee_arrears');
        }

        if ($exeatType->blocks_on_suspension && $data->isSuspended) {
            throw ExeatBlockedException::forReason($data->studentId, 'suspension');
        }

        if ($data->collectingGuardianId === null && $data->collectingPersonName !== null) {
            if ($data->oneOffAuthorisationBy === null) {
                throw OneOffAuthorisationRequiredException::forGuardian($data->studentId, 0);
            }

            $authorising = StudentGuardian::query()
                ->where('student_id', $data->studentId)
                ->where('guardian_id', $data->oneOffAuthorisationBy)
                ->where('status', 'active')
                ->first();

            if ($authorising === null || ! $authorising->may_authorise_exeat) {
                throw OneOffAuthorisationRequiredException::forGuardian($data->studentId, $data->oneOffAuthorisationBy);
            }
        }

        if ($exeatType->counts_toward_quota) {
            $quota = ExeatQuota::query()
                ->where('student_id', $data->studentId)
                ->where('term_id', $data->termId)
                ->where('exeat_type_id', $exeatType->id)
                ->first();

            $allowed = $quota === null ? $exeatType->allowed_per_term : $quota->allowed;

            if ($allowed !== null) {
                $used = $quota === null ? 0 : ($quota->used + $quota->pending);

                if ($used >= $allowed && ! $data->overrideQuota) {
                    throw ExeatQuotaExceededException::forStudent($data->studentId, $exeatType->id);
                }

                if ($data->overrideQuota && ($data->overrideReason === null || mb_strlen($data->overrideReason) < 10)) {
                    throw new InvalidArgumentException('Overriding the exeat quota requires a recorded reason of at least 10 characters.');
                }
            }
        }

        return $this->transaction(function () use ($data, $exeatType): Exeat {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'exeat',
                allocatedByUserId: $data->requestedByUserId ?? $data->requestedByGuardianId ?? 0,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
            ));

            $exeat = Exeat::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'exeat_number' => $number->formatted_number,
                'student_id' => $data->studentId,
                'exeat_type_id' => $exeatType->id,
                'requested_by_guardian_id' => $data->requestedByGuardianId,
                'requested_by_user_id' => $data->requestedByUserId,
                'request_source' => $data->requestSource,
                'reason' => $data->reason,
                'supporting_document_id' => $data->supportingDocumentId,
                'departs_at' => $data->departsAt,
                'returns_by' => $data->returnsBy,
                'destination_address' => $data->destinationAddress,
                'destination_city' => $data->destinationCity,
                'destination_province' => $data->destinationProvince,
                'destination_country' => $data->destinationCountry,
                'contact_phone' => $data->contactPhone,
                'collecting_guardian_id' => $data->collectingGuardianId,
                'collecting_person_name' => $data->collectingPersonName,
                'collecting_person_id_no' => $data->collectingPersonIdNo,
                'collecting_person_phone' => $data->collectingPersonPhone,
                'collection_method' => $data->collectionMethod,
                'one_off_authorisation_by' => $data->oneOffAuthorisationBy,
                'status' => 'pending',
            ]);

            if ($exeatType->counts_toward_quota) {
                $quota = ExeatQuota::firstOrCreate(
                    ['school_id' => $data->schoolId, 'student_id' => $data->studentId, 'term_id' => $data->termId, 'exeat_type_id' => $exeatType->id],
                    ['allowed' => $exeatType->allowed_per_term ?? 0],
                );
                $quota->increment('pending');
            }

            event(new ExeatRequested($exeat));

            return $exeat;
        });
    }
}
