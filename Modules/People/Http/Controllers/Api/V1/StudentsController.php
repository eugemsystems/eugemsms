<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Boarding\Domain\DataObjects\CollectionClaim;
use Modules\Boarding\Domain\Support\CollectionAuthorityChecker;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentTimelineEvent;

/**
 * Book C PPL-01 §9 / PPL-03 §8's staff-scoped student surface: `GET /students`, `GET
 * /students/{ulid}`, `.../timeline`, `.../enrolments`, `.../guardians`, and
 * `.../collection-authorised`. Every route sits behind `serp.api`, so it is already scoped to the
 * signed-in staff member's own school — these are deliberately plain reads, matching the backend's
 * own shape (confirmed: no Action exists for any of these queries, only for the writes that
 * created the rows, per `Modules\People\Livewire\Students\{Index,Show}`'s own direct-query
 * precedent).
 */
final class StudentsController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'per_page' => ['nullable', 'integer'], 'page' => ['nullable', 'integer']]);

        $query = Student::query()->orderBy('last_name')->orderBy('first_name');

        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('admission_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        $page = $query->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        return ApiResponse::page($page->getCollection()->map(fn (Student $student): array => [
            'id' => $student->ulid,
            'admission_number' => $student->admission_number,
            'name' => $student->fullName(),
            'grade_level_id' => $student->grade_level_id,
            'status' => $student->status,
        ])->values()->all(), $page);
    }

    public function show(string $student): JsonResponse
    {
        $found = $this->findOrFail($student);

        return ApiResponse::ok([
            'id' => $found->ulid,
            'admission_number' => $found->admission_number,
            'first_name' => $found->first_name,
            'middle_names' => $found->middle_names,
            'last_name' => $found->last_name,
            'date_of_birth' => $found->date_of_birth->toDateString(),
            'gender' => $found->gender,
            'nationality' => $found->nationality,
            'enrolment_type' => $found->enrolment_type,
            'residency' => $found->residency,
            'grade_level_id' => $found->grade_level_id,
            'class_id' => $found->class_id,
            'house_id' => $found->house_id,
            'pathway' => $found->pathway,
            'status' => $found->status,
            'enrolled_on' => $found->enrolled_on?->toDateString(),
        ]);
    }

    public function timeline(Request $request, string $student): JsonResponse
    {
        $found = $this->findOrFail($student);
        $request->validate(['category' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $events = StudentTimelineEvent::query()
            ->where('student_id', $found->id)
            ->when($request->filled('category'), fn ($q) => $q->where('event_category', $request->string('category')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('occurred_at', '<=', $request->date('to')))
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        return ApiResponse::ok($events->map(fn (StudentTimelineEvent $event): array => [
            'category' => $event->event_category,
            'type' => $event->event_type,
            'title' => $event->title,
            'summary' => $event->summary,
            'severity' => $event->severity,
            'occurred_at' => $event->occurred_at->toIso8601ZuluString(),
        ])->values()->all());
    }

    public function enrolments(string $student): JsonResponse
    {
        $found = $this->findOrFail($student);

        $enrolments = StudentEnrolment::query()->where('student_id', $found->id)->orderByDesc('started_on')->get();

        return ApiResponse::ok($enrolments->map(fn (StudentEnrolment $enrolment): array => [
            'academic_year_id' => $enrolment->academic_year_id,
            'term_id' => $enrolment->term_id,
            'grade_level_id' => $enrolment->grade_level_id,
            'class_id' => $enrolment->class_id,
            'enrolment_type' => $enrolment->enrolment_type,
            'residency' => $enrolment->residency,
            'status' => $enrolment->status,
            'started_on' => $enrolment->started_on?->toDateString(),
            'ended_on' => $enrolment->ended_on?->toDateString(),
            'is_repeat' => $enrolment->is_repeat,
        ])->values()->all());
    }

    public function guardians(string $student): JsonResponse
    {
        $found = $this->findOrFail($student);

        $links = StudentGuardian::query()->where('student_id', $found->id)->with('guardian')->orderByDesc('status')->get();

        return ApiResponse::ok($links->map(fn (StudentGuardian $link): array => [
            'guardian_id' => $link->guardian?->ulid,
            'name' => $link->guardian?->displayName(),
            'relationship' => $link->relationship,
            'status' => $link->status,
            'is_primary_contact' => $link->is_primary_contact,
            'is_emergency_contact' => $link->is_emergency_contact,
            'is_fee_responsible' => $link->is_fee_responsible,
            'may_collect_learner' => $link->canCollectLearner(),
            'may_authorise_exeat' => $link->may_authorise_exeat,
            'may_authorise_medical' => $link->may_authorise_medical,
            'may_view_full_balance' => $link->may_view_full_balance,
            'has_court_restriction' => $link->has_court_restriction,
        ])->values()->all());
    }

    /**
     * Read-only: the real, side-effecting collection check `Boarding\Gate\Terminal` runs is
     * `RecordDepartureAction` (it also logs the attempt and marks the exeat departed on release) —
     * a GET must stay side-effect-free, so this calls the same decision engine
     * (`CollectionAuthorityChecker`) directly without persisting anything.
     */
    public function collectionAuthorised(Request $request): JsonResponse
    {
        $student = $this->findOrFail((string) $request->route('student'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'guardian_id' => ['nullable', 'string'],
            'id_no' => ['nullable', 'string', 'max:30'],
            'identity_verified' => ['boolean'],
        ]);

        $guardianId = null;

        if (isset($data['guardian_id'])) {
            $guardianId = Guardian::query()->where('ulid', $data['guardian_id'])->value('id');
        }

        $exeat = Exeat::query()->where('student_id', $student->id)->whereIn('status', ['approved', 'departed'])->orderByDesc('departs_at')->first();

        $decision = app(CollectionAuthorityChecker::class)->check(
            $student,
            new CollectionClaim(name: $data['name'], guardianId: $guardianId, idNo: $data['id_no'] ?? null, identityVerified: (bool) ($data['identity_verified'] ?? false)),
            $exeat,
        );

        return ApiResponse::ok([
            'released' => $decision->released,
            'refusal_reason' => $decision->refusalReason,
        ]);
    }

    private function findOrFail(string $ulid): Student
    {
        $student = Student::query()->where('ulid', $ulid)->first();
        abort_if($student === null, 404);

        return $student;
    }
}
