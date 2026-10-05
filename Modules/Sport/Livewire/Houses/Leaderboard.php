<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Houses;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\House;
use Modules\Core\Models\School;
use Modules\Sport\Domain\Actions\ComputeHouseLeaderboardAction;
use Modules\Sport\Domain\Actions\CreateHouseCompetitionAction;
use Modules\Sport\Domain\Actions\RecordHouseCompetitionResultAction;
use Modules\Sport\Domain\Actions\RecordManualHousePointsAction;
use Modules\Sport\Domain\DataObjects\CreateHouseCompetitionData;
use Modules\Sport\Domain\DataObjects\RecordHouseCompetitionResultData;
use Modules\Sport\Domain\DataObjects\RecordManualHousePointsData;
use Modules\Sport\Models\HouseCompetition;

/**
 * `Houses\Leaderboard` (Book H2 OPS-07 §4 ⭐/BR-OPS-07-008/009,
 * `activities.manage`) — named `Leaderboard`, matching the spec's own
 * `Ops\Houses\Leaderboard` component name, NOT the spec's bare
 * `Houses\Index`: a real cross-module Livewire component-name
 * collision was found and fixed in this pass against
 * `Modules\Core\Livewire\Houses\Index` (Book A CORE-02's own house
 * register screen) — see `.ai/rules/sport.md`. The spec's own screen
 * table names only a read-only leaderboard (`activities.view`) —
 * creating a competition and recording manual points have no screen
 * of their own anywhere in the table, even though
 * `CreateHouseCompetitionAction`/`RecordHouseCompetitionResultAction`/
 * `RecordManualHousePointsAction` all exist. Folded in here rather
 * than left unreachable. `ComputeHouseLeaderboardAction` itself is
 * the live total; this screen never keeps its own running score.
 */
#[Title('House leaderboard')]
#[Layout('layouts.app')]
final class Leaderboard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $competitionName = '';

    public string $competitionType = 'sport';

    /** @var array<int, int> */
    public array $pointsScheme = [1 => 10, 2 => 7, 3 => 5];

    public ?int $manualHouseId = null;

    public string $manualPoints = '';

    public ?string $manualReason = null;

    public ?int $resultCompetitionId = null;

    public ?int $resultHouseId = null;

    public int $resultPlace = 1;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('activities.manage');
    }

    public function createCompetition(): void
    {
        $this->validate([
            'competitionName' => ['required', 'string'],
            'competitionType' => ['required', 'string'],
        ]);

        app(CreateHouseCompetitionAction::class)->execute(new CreateHouseCompetitionData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            name: $this->competitionName,
            competitionType: $this->competitionType,
            pointsScheme: $this->pointsScheme,
            heldOn: Carbon::now(),
        ));

        $this->reset(['competitionName']);
        $this->toast(__('House competition created.'));
    }

    public function recordManualPoints(): void
    {
        $this->validate([
            'manualHouseId' => ['required', 'integer'],
            'manualPoints' => ['required', 'numeric'],
        ]);

        app(RecordManualHousePointsAction::class)->execute(new RecordManualHousePointsData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            houseId: (int) $this->manualHouseId,
            points: (float) $this->manualPoints,
            awardedByUserId: (int) auth()->id(),
            reason: $this->manualReason,
        ));

        $this->reset(['manualPoints', 'manualReason']);
        $this->toast(__('Points recorded.'));
    }

    public function recordCompetitionResult(): void
    {
        $this->validate([
            'resultCompetitionId' => ['required', 'integer'],
            'resultHouseId' => ['required', 'integer'],
            'resultPlace' => ['required', 'integer', 'gt:0'],
        ]);

        app(RecordHouseCompetitionResultAction::class)->execute(new RecordHouseCompetitionResultData(
            competitionId: (int) $this->resultCompetitionId,
            placements: [['house_id' => (int) $this->resultHouseId, 'place' => $this->resultPlace]],
            termId: (int) SessionContext::termId(),
            awardedByUserId: (int) auth()->id(),
        ));

        $this->toast(__('Competition result recorded.'));
    }

    public function render(): View
    {
        return view('sport::houses.leaderboard', [
            'leaderboard' => app(ComputeHouseLeaderboardAction::class)->execute($this->school->id, (int) SessionContext::yearId()),
            'houses' => House::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'competitions' => HouseCompetition::where('school_id', $this->school->id)->orderByDesc('id')->limit(20)->get(),
        ]);
    }
}
