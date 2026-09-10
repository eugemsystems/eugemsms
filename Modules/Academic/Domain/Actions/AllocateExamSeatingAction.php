<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\AllocateExamSeatingData;
use Modules\Academic\Domain\Exceptions\VenueExamCapacityExceededException;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSeating;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-AllocateExamSeating (Book E ACA-07 §4/BR-ACA-07-005/006/007).
 * A candidate with an approved `separate_room` arrangement for this
 * paper is seated alone in their own venue first (BR-ACA-07-007);
 * the remaining general cohort is then spread across the remaining
 * venues honouring `exams.seating_spacing_seats` — seat numbers skip
 * that many physical seats between consecutively seated candidates.
 * "Avoids seating classmates adjacently where possible" is a
 * best-effort heuristic here (round-robin by class), not a guarantee
 * — the rule's own wording.
 */
final class AllocateExamSeatingAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, ExaminationSeating>
     */
    public function execute(AllocateExamSeatingData $data): Collection
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        $candidates = ExaminationCandidate::query()
            ->where('school_id', $paper->school_id)
            ->where('session_id', $paper->session_id)
            ->where('entry_status', 'confirmed')
            ->get()
            ->filter(fn (ExaminationCandidate $c): bool => in_array($paper->subject_id, $c->entered_subjects, true));

        $separateRoomStudentIds = SpecialArrangement::query()
            ->where('school_id', $paper->school_id)
            ->where('session_id', $paper->session_id)
            ->where('arrangement_type', 'separate_room')
            ->where('status', 'approved')
            ->get()
            ->filter(fn (SpecialArrangement $a): bool => $a->applies_to_papers === null || in_array($paper->id, $a->applies_to_papers, true))
            ->pluck('student_id');

        $separateRoomCandidates = $candidates->filter(fn (ExaminationCandidate $c): bool => $separateRoomStudentIds->contains($c->student_id))->values();
        $generalCandidates = $candidates->reject(fn (ExaminationCandidate $c): bool => $separateRoomStudentIds->contains($c->student_id))->values();

        $venues = Venue::findMany($data->venueIds);
        $requiredVenues = $separateRoomCandidates->count();

        if ($venues->count() < $requiredVenues) {
            throw VenueExamCapacityExceededException::forPaper($paper->id, $separateRoomCandidates->count(), $venues->count());
        }

        $separateVenues = $venues->slice(0, $requiredVenues)->values();
        $generalVenues = $venues->slice($requiredVenues)->values();

        $generalCapacity = (int) $generalVenues->sum(fn (Venue $v): int => $v->exam_capacity ?? $v->capacity);

        if ($generalCandidates->count() > $generalCapacity) {
            throw VenueExamCapacityExceededException::forPaper($paper->id, $generalCandidates->count(), $generalCapacity);
        }

        $spacing = (int) $this->settings->get('exams.seating_spacing_seats', new ScopeChain(schoolId: $paper->school_id));

        return $this->transaction(function () use ($paper, $separateRoomCandidates, $separateVenues, $generalCandidates, $generalVenues, $spacing): Collection {
            $seatings = collect();

            foreach ($separateRoomCandidates as $index => $candidate) {
                $seatings->push($this->seat($paper, $candidate, $separateVenues[$index], 1));
            }

            $seatIndex = 1;
            $venueIndex = 0;
            $remainingCapacity = $generalVenues->isEmpty() ? 0 : ($generalVenues[0]->exam_capacity ?? $generalVenues[0]->capacity);

            foreach ($generalCandidates as $candidate) {
                while ($remainingCapacity <= 0 && $venueIndex < $generalVenues->count() - 1) {
                    $venueIndex++;
                    $seatIndex = 1;
                    $remainingCapacity = $generalVenues[$venueIndex]->exam_capacity ?? $generalVenues[$venueIndex]->capacity;
                }

                $seatings->push($this->seat($paper, $candidate, $generalVenues[$venueIndex], $seatIndex));
                $seatIndex += 1 + $spacing;
                $remainingCapacity--;
            }

            return $seatings;
        });
    }

    private function seat(ExaminationPaper $paper, ExaminationCandidate $candidate, Venue $venue, int $seatIndex): ExaminationSeating
    {
        return ExaminationSeating::updateOrCreate(
            ['school_id' => $paper->school_id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id],
            ['venue_id' => $venue->id, 'seat_number' => 'S'.$seatIndex],
        );
    }
}
