<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Households;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\AddHouseholdMemberAction;
use Modules\People\Domain\Actions\CreateHouseholdAction;
use Modules\People\Domain\Actions\RemoveHouseholdMemberAction;
use Modules\People\Domain\Actions\UpdateHouseholdAction;
use Modules\People\Domain\DataObjects\AddHouseholdMemberData;
use Modules\People\Domain\DataObjects\CreateHouseholdData;
use Modules\People\Domain\DataObjects\RemoveHouseholdMemberData;
use Modules\People\Domain\DataObjects\UpdateHouseholdData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Household;
use Modules\People\Models\HouseholdMember;
use Modules\People\Models\Student;

/**
 * `People\Households\Index` (Book C PPL-03 §7, `people.guardians.household_manage`).
 * Families grouped for sibling discounts and combined statements. Membership
 * is dated; a learner is in one household at a time. Members are found by
 * search and re-resolved server-side.
 */
#[Title('Households')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public ?int $selectedId = null;

    public string $memberType = 'student';

    public string $search = '';

    public bool $siblingDiscountEligible = true;

    public bool $combinedStatement = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.household_manage');
    }

    public function create(): void
    {
        $this->authorizePermission('people.guardians.household_manage');
        $this->resetErrorBag();

        try {
            $household = app(CreateHouseholdAction::class)->execute(new CreateHouseholdData($this->school->id, $this->name));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->reset('name');
        $this->open($household->id);
        $this->toast(__('Household created.'));
    }

    public function open(int $householdId): void
    {
        $household = Household::query()->find($householdId);

        $this->selectedId = $household?->id;
        $this->siblingDiscountEligible = (bool) $household?->sibling_discount_eligible;
        $this->combinedStatement = (bool) $household?->combined_statement;
        $this->search = '';
    }

    public function saveSettings(): void
    {
        $this->authorizePermission('people.guardians.household_manage');

        $household = $this->selectedId === null ? null : Household::query()->find($this->selectedId);

        if ($household === null) {
            return;
        }

        app(UpdateHouseholdAction::class)->execute(new UpdateHouseholdData($household->id, $household->name, $household->head_guardian_id, $this->combinedStatement, $this->siblingDiscountEligible, $household->address_line_1, $household->city));
        $this->toast(__('Saved.'));
    }

    public function addMember(int $memberId): void
    {
        $this->authorizePermission('people.guardians.household_manage');

        if ($this->selectedId === null) {
            return;
        }

        try {
            app(AddHouseholdMemberAction::class)->execute(new AddHouseholdMemberData($this->selectedId, $this->memberType, $memberId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->search = '';
        $this->toast(__('Added.'));
    }

    public function removeMember(string $memberType, int $memberId): void
    {
        $this->authorizePermission('people.guardians.household_manage');

        if ($this->selectedId === null) {
            return;
        }

        try {
            app(RemoveHouseholdMemberAction::class)->execute(new RemoveHouseholdMemberData($this->selectedId, $memberType, $memberId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Removed.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $members = $this->selectedId === null ? collect() : HouseholdMember::query()->where('household_id', $this->selectedId)->whereNull('left_on')->get();

        return view('people::households.index', [
            'households' => Household::query()->withCount(['members as active_members' => fn ($q) => $q->whereNull('left_on')])->orderBy('name')->limit(100)->get(),
            'members' => $members,
            'students' => Student::query()->whereIn('id', $members->where('member_type', 'student')->pluck('member_id'))->get()->keyBy('id'),
            'guardians' => Guardian::query()->whereIn('id', $members->where('member_type', 'guardian')->pluck('member_id'))->get()->keyBy('id'),
            'matches' => mb_strlen($term) < 2 || $this->selectedId === null ? collect() : ($this->memberType === 'student'
                ? Student::query()->where(fn ($q) => $q->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->limit(8)->get()->map(fn (Student $s): array => ['id' => $s->id, 'label' => "{$s->admission_number} — {$s->fullName()}"])
                : Guardian::query()->where('status', '!=', 'merged')->where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('organisation_name', 'like', $like))->limit(8)->get()->map(fn (Guardian $g): array => ['id' => $g->id, 'label' => $g->displayName()])),
        ]);
    }
}
