<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1\Public;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\Actions\Scheduling\ResolveSystemActorAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\AttachApplicationDocumentAction;
use Modules\People\Domain\Actions\AttachStudentDocumentAction;
use Modules\People\Domain\Actions\CreateEnquiryAction;
use Modules\People\Domain\Actions\SubmitApplicationAction;
use Modules\People\Domain\DataObjects\AttachApplicationDocumentData;
use Modules\People\Domain\DataObjects\CreateEnquiryData;
use Modules\People\Domain\DataObjects\SubmitApplicationData;
use Modules\People\Models\Application;
use Modules\People\Models\Intake;

/**
 * Book C PPL-02 §6/BR-PPL-02-001/015. The public, unauthenticated REST surface the spec names
 * (`POST .../applications`, `GET .../intakes`, `GET .../applications/track`,
 * `POST .../applications/{ulid}/documents`, `POST .../enquiries`) — distinct from the existing
 * `/apply/{slug}` Blade form (`PublicEnquiryController`), which only ever creates an `enquiries`
 * row. This controller's own `applications` endpoint is the one that satisfies BR-PPL-02-001's
 * literal "writes only to `applications`. It can never touch `students`" — confirmed by
 * `SubmitApplicationAction` itself, which never creates a `Student` row.
 *
 * No real CAPTCHA service is wired into this project (no dependency for one is approved) — every
 * write endpoint reuses the same honeypot-plus-minimum-fill-time mechanism
 * `PublicEnquiryController` already established, which is this codebase's own existing
 * interpretation of BR-PPL-02-001's "CAPTCHA-protected". `started_at` is a unix timestamp the
 * caller echoes back from when the form was first shown.
 *
 * "Signed upload" for the document endpoint is interpreted the same way BR-PPL-02-015's own
 * tracking link already is: proof of ownership is the application's `date_of_birth` matching what
 * the caller submits, not a literal signed URL — no signed-URL-for-anonymous-POST mechanism exists
 * anywhere else in this codebase to reuse, and inventing a parallel one for this single endpoint
 * would be a second, undocumented convention.
 *
 * Every endpoint resolves its own school from a public, non-guessable identifier it is given (a
 * school's own `ulid`, or an intake's own `public_form_slug` — the same slug the web form already
 * uses) — never from anything else the caller sends, mirroring `PublicEnquiryController`'s own
 * documented rule.
 */
final class PublicApplicationsController
{
    public function intakes(Request $request): JsonResponse
    {
        $school = School::query()->where('ulid', (string) $request->query('school'))->first();

        if ($school === null) {
            return ApiResponse::error('NOT_FOUND', 'Unknown school.', 404);
        }

        $intakes = Intake::query()->withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('public_form_enabled', true)
            ->where('status', 'open')
            ->whereDate('opens_on', '<=', now())
            ->whereDate('closes_on', '>=', now())
            ->with('gradeLevel:id,name')
            ->get();

        return ApiResponse::ok($intakes->map(fn (Intake $intake): array => [
            'slug' => $intake->public_form_slug,
            'name' => $intake->name,
            'grade_level' => $intake->gradeLevel?->name,
            'closes_on' => $intake->closes_on->toDateString(),
            'requires_entrance_exam' => $intake->requires_entrance_exam,
            'requires_interview' => $intake->requires_interview,
            'application_fee' => $intake->application_fee_minor === null ? null : [
                'amount_minor' => $intake->application_fee_minor,
                'currency' => $intake->application_fee_currency,
            ],
        ])->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $intake = $this->openIntake((string) $request->string('intake_slug'));

        if ($intake === null) {
            return ApiResponse::error('NOT_FOUND', 'Unknown or closed intake.', 404);
        }

        if ($this->isSpam($request)) {
            // Never reveal why a spam submission was dropped (same rule as the web form).
            return ApiResponse::ok(['status' => 'submitted'], status: 202);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_names' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'national_registration_no' => ['nullable', 'string', 'max:30'],
            'birth_certificate_no' => ['nullable', 'string', 'max:30'],
            'requested_enrolment_type' => ['required', 'string'],
            'requested_residency' => ['required', 'string'],
            'requested_pathway' => ['nullable', 'string'],
            'requested_subjects' => ['nullable', 'array'],
            'has_sibling_at_school' => ['boolean'],
            'guardian_is_alumnus' => ['boolean'],
            'guardian_is_staff' => ['boolean'],
            'guardians' => ['required', 'array', 'min:1'],
            'guardians.*.relationship' => ['required', 'string'],
            'guardians.*.first_name' => ['nullable', 'string', 'max:80'],
            'guardians.*.last_name' => ['nullable', 'string', 'max:80'],
            'guardians.*.organisation_name' => ['nullable', 'string', 'max:150'],
            'guardians.*.primary_phone' => ['nullable', 'string', 'max:20'],
            'guardians.*.email' => ['nullable', 'email', 'max:150'],
            'guardians.*.is_primary_contact' => ['boolean'],
            'guardians.*.is_fee_responsible' => ['boolean'],
        ]);

        SchoolContext::set(School::query()->withoutGlobalScopes()->findOrFail($intake->school_id));

        try {
            $application = app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
                schoolId: $intake->school_id,
                intakeId: $intake->id,
                firstName: $data['first_name'],
                lastName: $data['last_name'],
                dateOfBirth: CarbonImmutable::parse($data['date_of_birth']),
                gender: $data['gender'],
                requestedGradeLevelId: $intake->grade_level_id,
                requestedEnrolmentType: $data['requested_enrolment_type'],
                requestedResidency: $data['requested_residency'],
                guardians: $data['guardians'],
                createdByUserId: app(ResolveSystemActorAction::class)->execute(),
                middleNames: $data['middle_names'] ?? null,
                nationality: $data['nationality'] ?? 'ZW',
                nationalRegistrationNo: $data['national_registration_no'] ?? null,
                birthCertificateNo: $data['birth_certificate_no'] ?? null,
                requestedPathway: $data['requested_pathway'] ?? null,
                requestedSubjectIds: $data['requested_subjects'] ?? null,
                hasSiblingAtSchool: (bool) ($data['has_sibling_at_school'] ?? false),
                guardianIsAlumnus: (bool) ($data['guardian_is_alumnus'] ?? false),
                guardianIsStaff: (bool) ($data['guardian_is_staff'] ?? false),
            ));
        } finally {
            SchoolContext::clear();
        }

        return ApiResponse::ok([
            'application_number' => $application->application_number,
            'ulid' => $application->ulid,
            'status' => $application->status,
        ], status: 201);
    }

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_number' => ['required', 'string'],
            'date_of_birth' => ['required', 'date'],
        ]);

        $application = Application::query()->withoutGlobalScopes()
            ->where('application_number', $data['application_number'])
            ->whereDate('date_of_birth', CarbonImmutable::parse($data['date_of_birth'])->toDateString())
            ->first();

        if ($application === null) {
            return ApiResponse::error('NOT_FOUND', 'No application matches that number and date of birth.', 404);
        }

        return ApiResponse::ok([
            'application_number' => $application->application_number,
            'status' => $application->status,
            'submitted_at' => $application->submitted_at?->toIso8601ZuluString(),
            'offer_expires_at' => $application->offer_expires_at?->toIso8601ZuluString(),
            'waitlist_position' => $application->waitlist_position,
        ]);
    }

    public function storeDocument(Request $request, string $ulid): JsonResponse
    {
        $application = Application::query()->withoutGlobalScopes()->where('ulid', $ulid)->first();

        if ($application === null) {
            return ApiResponse::error('NOT_FOUND', 'Unknown application.', 404);
        }

        if ($this->isSpam($request)) {
            return ApiResponse::ok(['status' => 'received'], status: 202);
        }

        $data = $request->validate([
            'date_of_birth' => ['required', 'date'],
            'document_type' => ['required', 'string', 'in:'.implode(',', AttachStudentDocumentAction::TYPES)],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        if (! $application->date_of_birth->isSameDay(CarbonImmutable::parse($data['date_of_birth']))) {
            return ApiResponse::error('UNAUTHENTICATED', 'That date of birth does not match this application.', 401);
        }

        $school = School::query()->withoutGlobalScopes()->findOrFail($application->school_id);
        SchoolContext::set($school);

        try {
            $file = app(UploadFileAction::class)->execute(new UploadFileData(
                schoolId: $application->school_id,
                category: 'application_document',
                contents: (string) $request->file('file')?->get(),
                originalName: (string) $request->file('file')?->getClientOriginalName(),
                uploadedByUserId: app(ResolveSystemActorAction::class)->execute(),
                attachableType: 'application',
                attachableId: $application->id,
            ));

            try {
                app(AttachApplicationDocumentAction::class)->execute(new AttachApplicationDocumentData(
                    schoolId: $application->school_id,
                    applicationId: $application->id,
                    documentType: $data['document_type'],
                    fileId: $file->id,
                ));
            } catch (InvalidArgumentException $exception) {
                return ApiResponse::error('VALIDATION_FAILED', $exception->getMessage(), 422);
            }
        } finally {
            SchoolContext::clear();
        }

        return ApiResponse::ok(['status' => 'received'], status: 201);
    }

    public function storeEnquiry(Request $request): JsonResponse
    {
        $school = School::query()->where('ulid', (string) $request->string('school'))->first();

        if ($school === null) {
            return ApiResponse::error('NOT_FOUND', 'Unknown school.', 404);
        }

        if ($this->isSpam($request)) {
            return ApiResponse::ok(['status' => 'received'], status: 202);
        }

        $data = $request->validate([
            'intake_slug' => ['nullable', 'string'],
            'enquirer_name' => ['required', 'string', 'max:120'],
            'enquirer_phone' => ['nullable', 'string', 'max:20', 'required_without:enquirer_email'],
            'enquirer_email' => ['nullable', 'email', 'max:150', 'required_without:enquirer_phone'],
            'learner_name' => ['nullable', 'string', 'max:120'],
            'learner_dob' => ['nullable', 'date', 'before:today'],
            'interested_grade_level_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $intake = isset($data['intake_slug']) ? $this->openIntake($data['intake_slug']) : null;
        SchoolContext::set($school);

        try {
            app(CreateEnquiryAction::class)->execute(new CreateEnquiryData(
                schoolId: $school->id,
                source: 'website',
                enquirerName: $data['enquirer_name'],
                enquirerPhone: $data['enquirer_phone'] ?? null,
                enquirerEmail: $data['enquirer_email'] ?? null,
                learnerName: $data['learner_name'] ?? null,
                learnerDob: isset($data['learner_dob']) ? CarbonImmutable::parse($data['learner_dob']) : null,
                interestedGradeLevelId: $data['interested_grade_level_id'] ?? $intake?->grade_level_id,
                message: $data['message'] ?? null,
                intakeId: $intake?->id,
            ));
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('VALIDATION_FAILED', $exception->getMessage(), 422);
        } finally {
            SchoolContext::clear();
        }

        return ApiResponse::ok(['status' => 'received'], status: 201);
    }

    private function openIntake(string $slug): ?Intake
    {
        if ($slug === '') {
            return null;
        }

        return Intake::query()->withoutGlobalScopes()
            ->where('public_form_slug', $slug)
            ->where('public_form_enabled', true)
            ->where('status', 'open')
            ->whereDate('opens_on', '<=', now())
            ->whereDate('closes_on', '>=', now())
            ->first();
    }

    private function isSpam(Request $request): bool
    {
        $startedAt = $request->integer('started_at');

        return $request->filled('website') || ($startedAt > 0 && $startedAt > now()->getTimestamp() - 3);
    }
}
