<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Public;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateEnquiryAction;
use Modules\People\Domain\DataObjects\CreateEnquiryData;
use Modules\People\Models\Intake;

/**
 * BR-PPL-02-001's public form, deliberately limited to the top of the funnel: an unauthenticated
 * visitor leaves an enquiry (source `website`) against an open intake, and staff take it from there.
 * A full application needs documents, fees and a verified guardian, so it is never created
 * anonymously. The intake is found by its globally unique `public_form_slug`; the school it belongs
 * to comes from that row, never from anything the visitor sends. Abuse is handled by the throttle on
 * the route, a honeypot field and a minimum time-to-fill; none of them reveal why a post was dropped.
 */
final class PublicEnquiryController
{
    public function show(string $slug): View
    {
        $intake = $this->openIntake($slug);

        return view('people::public.enquiry', [
            'intake' => $intake,
            'school' => School::query()->findOrFail($intake->school_id),
            'grades' => $this->grades($intake),
            'startedAt' => now()->timestamp,
            'sent' => false,
        ]);
    }

    public function store(Request $request, string $slug, CreateEnquiryAction $create): RedirectResponse
    {
        $intake = $this->openIntake($slug);

        $data = $request->validate([
            'enquirer_name' => ['required', 'string', 'max:120'],
            'enquirer_phone' => ['nullable', 'string', 'max:20', 'required_without:enquirer_email'],
            'enquirer_email' => ['nullable', 'email', 'max:150', 'required_without:enquirer_phone'],
            'learner_name' => ['nullable', 'string', 'max:120'],
            'learner_dob' => ['nullable', 'date', 'before:today'],
            'interested_grade_level_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:1000'],
            'started_at' => ['nullable', 'integer'],
        ]);

        $tooFast = isset($data['started_at']) && (int) $data['started_at'] > now()->getTimestamp() - 3;

        if ($request->filled('website') || $tooFast) {
            return redirect()->route('people.public.enquiry.show', $slug)->with('enquiry_sent', true);
        }

        SchoolContext::set(School::query()->findOrFail($intake->school_id));

        try {
            $create->execute(new CreateEnquiryData(
                schoolId: $intake->school_id,
                source: 'website',
                enquirerName: $data['enquirer_name'],
                enquirerPhone: $data['enquirer_phone'] ?? null,
                enquirerEmail: $data['enquirer_email'] ?? null,
                learnerName: $data['learner_name'] ?? null,
                learnerDob: isset($data['learner_dob']) ? CarbonImmutable::parse($data['learner_dob']) : null,
                interestedGradeLevelId: $this->grades($intake)->contains('id', (int) ($data['interested_grade_level_id'] ?? 0))
                    ? (int) $data['interested_grade_level_id'] : $intake->grade_level_id,
                message: $data['message'] ?? null,
                intakeId: $intake->id,
            ));
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['enquirer_phone' => $exception->getMessage()]);
        } finally {
            SchoolContext::clear();
        }

        return redirect()->route('people.public.enquiry.show', $slug)->with('enquiry_sent', true);
    }

    private function openIntake(string $slug): Intake
    {
        $intake = Intake::query()->withoutGlobalScopes()
            ->where('public_form_slug', $slug)
            ->where('public_form_enabled', true)
            ->where('status', 'open')
            ->whereDate('opens_on', '<=', now())
            ->whereDate('closes_on', '>=', now())
            ->first();

        abort_if($intake === null, 404);

        return $intake;
    }

    /**
     * @return Collection<int, GradeLevel>
     */
    private function grades(Intake $intake): Collection
    {
        return GradeLevel::query()->withoutGlobalScopes()->where('school_id', $intake->school_id)->orderBy('id')->get(['id', 'name']);
    }
}
