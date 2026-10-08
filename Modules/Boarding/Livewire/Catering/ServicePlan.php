<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CloseMealServiceAction;
use Modules\Boarding\Domain\Actions\PlanMealServiceAction;
use Modules\Boarding\Domain\DataObjects\CloseMealServiceData;
use Modules\Boarding\Domain\DataObjects\PlanMealServiceData;
use Modules\Boarding\Models\MealAttendance;
use Modules\Boarding\Models\MealService;
use Modules\Boarding\Models\MenuDay;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Catering\ServicePlan` (Book F BRD-04 §6 ⭐, `catering.service.manage`).
 * The single feature that pays for the module: servings compute from
 * `BRD-02`'s live present occupancy, never allocated beds. Folds in
 * the spec's separate "Requisition & issue" screen — in this
 * codebase's planning-only mode (`StoreIssuanceProvider` stubbed,
 * §0.3), there is no real stock to issue/return, so the fold costs
 * nothing: required-quantity lines are the whole of what exists.
 * `CloseMealServiceAction`'s own gate — a service cannot close
 * without `actual_served` — is enforced here as a hard validation,
 * not merely a hint. Where `catering.meal_attendance_capture` is
 * enabled, the count `Catering\ServingTerminal` has recorded is shown
 * and can be pulled in with one click — `actual_served` stays a
 * manually confirmed figure the kitchen manager can still override,
 * never silently auto-filled (BR-BRD-04-015/017).
 */
#[Title('Daily service plan')]
#[Layout('layouts.app')]
final class ServicePlan extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $serviceDate = '';

    public string $meal = 'lunch';

    public ?int $menuDayId = null;

    public int $staffMeals = 0;

    public int $guestMeals = 0;

    public ?int $actualServed = null;

    public string $wastageNote = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.service.view');
        $this->serviceDate = now()->toDateString();
    }

    public function plan(): void
    {
        $this->authorizePermission('boarding.catering.service.manage');

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(PlanMealServiceAction::class)->execute(new PlanMealServiceData(
            schoolId: $this->school->id,
            termId: $term->id,
            serviceDate: Carbon::parse($this->serviceDate),
            meal: $this->meal,
            currency: $this->school->base_currency,
            menuDayId: $this->menuDayId,
            staffMeals: $this->staffMeals,
            guestMeals: $this->guestMeals,
        ));

        $this->toast(__('Service planned from live occupancy.'));
    }

    public function useCapturedCount(int $mealServiceId): void
    {
        $this->actualServed = MealAttendance::where('meal_service_id', $mealServiceId)->where('attended', true)->count();
    }

    public function close(int $mealServiceId): void
    {
        $this->authorizePermission('boarding.catering.service.manage');

        if ($this->actualServed === null) {
            $this->toast(__('actual_served is required before a service can close.'), 'danger');

            return;
        }

        app(CloseMealServiceAction::class)->execute(new CloseMealServiceData(
            mealServiceId: $mealServiceId,
            actualServed: $this->actualServed,
            wastageNote: $this->wastageNote !== '' ? $this->wastageNote : null,
        ));

        $this->reset(['actualServed', 'wastageNote']);
        $this->toast(__('Service closed.'));
    }

    public function render(): View
    {
        $service = MealService::where('school_id', $this->school->id)
            ->whereDate('service_date', $this->serviceDate)
            ->where('meal', $this->meal)
            ->with('requisitionLines')
            ->first();

        $captureEnabled = (bool) app(SettingResolver::class)->get('catering.meal_attendance_capture', new ScopeChain(schoolId: $this->school->id));

        return view('boarding::catering.service-plan', [
            'service' => $service,
            'menuDays' => MenuDay::whereHas('cycle', fn ($q) => $q->where('school_id', $this->school->id))->get(),
            'captureEnabled' => $captureEnabled,
            'capturedCount' => $captureEnabled && $service !== null ? MealAttendance::where('meal_service_id', $service->id)->where('attended', true)->count() : null,
        ]);
    }
}
