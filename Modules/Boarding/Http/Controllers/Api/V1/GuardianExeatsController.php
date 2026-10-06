<?php

declare(strict_types=1);

namespace Modules\Boarding\Http\Controllers\Api\V1;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Boarding\Domain\Actions\RequestExeatAction;
use Modules\Boarding\Domain\DataObjects\RequestExeatData;
use Modules\Boarding\Domain\Support\ExeatEligibility;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Domain\Support\LinkedLearners;
use Modules\People\Models\Guardian;

/**
 * `/api/v1` exeats for guardians (Book F BRD-03). A guardian sees a boarder's exeats and asks for a
 * new one from the app. Only a guardian who may authorise exeats for that learner can ask, the
 * request goes through `RequestExeatAction` (quota, notice, arrears and suspension rules included),
 * and a collector who is not a registered guardian needs this guardian's own one-off authorisation.
 * The pass and verification code are shown only once the exeat is approved.
 */
final class GuardianExeatsController
{
    public function types(): JsonResponse
    {
        return ApiResponse::ok(ExeatType::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (ExeatType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'max_duration_hours' => $type->max_duration_hours,
                'min_notice_hours' => $type->min_notice_hours,
                'requires_document' => $type->requires_document,
            ])->values()->all());
    }

    public function index(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        return ApiResponse::ok(Exeat::query()->where('student_id', $link->student_id)->with('exeatType')
            ->orderByDesc('departs_at')->limit(50)->get()
            ->map(fn (Exeat $exeat): array => $this->exeat($exeat))->values()->all());
    }

    public function store(Request $request, string $student, LinkedLearners $linked, RequestExeatAction $requestExeat, ExeatEligibility $eligibility): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);
        abort_unless($link->may_authorise_exeat, 403, 'You are not authorised to request exeats for this learner.');

        $data = $request->validate([
            'exeat_type_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:1000'],
            'departs_at' => ['required', 'date', 'after:now'],
            'returns_by' => ['required', 'date', 'after:departs_at'],
            'destination_address' => ['required', 'string', 'max:255'],
            'destination_province' => ['required', 'string', 'max:60'],
            'destination_city' => ['nullable', 'string', 'max:80'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'collection_method' => ['required', 'string', 'in:guardian_collects,other_person_collects,self_transport'],
            'collecting_person_name' => ['nullable', 'string', 'max:120', 'required_if:collection_method,other_person_collects'],
            'collecting_person_id_no' => ['nullable', 'string', 'max:40'],
            'collecting_person_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $learner = $link->student;
        abort_unless(in_array($learner->residency, ['BOARDER', 'WEEKLY_BOARDER'], true), 422, 'Only boarders can be given an exeat.');
        abort_unless(ExeatType::query()->whereKey($data['exeat_type_id'])->where('is_active', true)->exists(), 422, 'That exeat type is not available.');

        $guardianId = (int) Guardian::query()->where('user_id', $user->id)->value('id');
        $oneOff = $data['collection_method'] === 'other_person_collects';

        $exeat = $requestExeat->execute(new RequestExeatData(
            schoolId: $learner->school_id,
            academicYearId: SessionContext::year()->id,
            termId: (int) SessionContext::termId(),
            studentId: $learner->id,
            exeatTypeId: $data['exeat_type_id'],
            reason: $data['reason'],
            departsAt: CarbonImmutable::parse($data['departs_at']),
            returnsBy: CarbonImmutable::parse($data['returns_by']),
            destinationAddress: $data['destination_address'],
            destinationProvince: $data['destination_province'],
            contactPhone: $data['contact_phone'],
            collectionMethod: $data['collection_method'],
            requestSource: 'guardian_app',
            requestedByGuardianId: $guardianId,
            collectingGuardianId: $oneOff ? null : $guardianId,
            collectingPersonName: $oneOff ? $data['collecting_person_name'] : null,
            collectingPersonIdNo: $oneOff ? ($data['collecting_person_id_no'] ?? null) : null,
            collectingPersonPhone: $oneOff ? ($data['collecting_person_phone'] ?? null) : null,
            oneOffAuthorisationBy: $oneOff ? $guardianId : null,
            destinationCity: $data['destination_city'] ?? null,
            feeArrearsExceedThreshold: $eligibility->arrearsExceedThreshold($learner),
            isSuspended: $eligibility->isSuspended($learner),
        ));

        return ApiResponse::ok($this->exeat($exeat->load('exeatType')), status: 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function exeat(Exeat $exeat): array
    {
        $row = [
            'id' => $exeat->ulid,
            'exeat_number' => $exeat->exeat_number,
            'type' => $exeat->exeatType->name,
            'status' => $exeat->status,
            'reason' => $exeat->reason,
            'departs_at' => $exeat->departs_at->toIso8601ZuluString(),
            'returns_by' => $exeat->returns_by->toIso8601ZuluString(),
            'destination' => $exeat->destination_address,
            'collection_method' => $exeat->collection_method,
            'rejection_reason' => $exeat->status === 'rejected' ? $exeat->rejection_reason : null,
            'actual_departure_at' => $exeat->actual_departure_at?->toIso8601ZuluString(),
            'actual_return_at' => $exeat->actual_return_at?->toIso8601ZuluString(),
        ];

        if ($exeat->status === 'approved') {
            $row['verification_code'] = $exeat->verification_code;
        }

        return $row;
    }
}
