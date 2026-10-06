<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\MergeGuardiansAction;
use Modules\People\Models\Guardian;

/**
 * `People\Guardians\Duplicates` (Book C PPL-03 BR-PPL-03-023, `guardians.merge`). Lists guardians
 * who share a normalised phone number, or the same name, as merge candidates for a person to
 * review. Nothing merges on its own: the reviewer picks which record stays and every other record
 * in the group is folded into it by `MergeGuardiansAction`.
 */
#[Title('Duplicate guardians')]
#[Layout('layouts.app')]
final class Duplicates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.merge');
    }

    public function merge(int $survivorId, int $duplicateId): void
    {
        $this->authorizePermission('people.guardians.merge');

        try {
            app(MergeGuardiansAction::class)->execute(
                Guardian::query()->where('school_id', $this->school->id)->findOrFail($survivorId)->id,
                Guardian::query()->where('school_id', $this->school->id)->findOrFail($duplicateId)->id,
                (int) auth()->id(),
            );
        } catch (ValidationException $e) {
            $this->toast(implode(' ', collect($e->errors())->flatten()->all()), 'danger');

            return;
        }

        $this->toast(__('Guardians merged.'));
    }

    /**
     * @return list<array{reason: string, members: Collection<int, Guardian>}>
     */
    private function groups(): array
    {
        $guardians = Guardian::query()->where('school_id', $this->school->id)->where('status', '!=', 'merged')
            ->where('guardian_type', 'individual')->orderBy('last_name')->limit(5000)->get();

        $byPhone = [];
        $byName = [];

        foreach ($guardians as $guardian) {
            $phone = trim((string) $guardian->primary_phone);

            if ($phone !== '') {
                $byPhone[PhoneNormalizer::toE164($phone) ?? $phone][] = $guardian;
            }

            $name = mb_strtolower(trim($guardian->first_name.' '.$guardian->last_name));

            if ($name !== '') {
                $byName[$name][] = $guardian;
            }
        }

        $groups = [];
        $grouped = [];

        foreach ($byPhone as $phone => $members) {
            if (count($members) > 1) {
                $groups[] = ['reason' => __('Same phone: :phone', ['phone' => (string) $phone]), 'members' => Collection::make($members)];
                array_push($grouped, ...array_map(fn (Guardian $g): int => $g->id, $members));
            }
        }

        foreach ($byName as $name => $members) {
            $members = array_values(array_filter($members, fn (Guardian $g): bool => ! in_array($g->id, $grouped, true)));

            if (count($members) > 1) {
                $groups[] = ['reason' => __('Same name: :name', ['name' => (string) $name]), 'members' => Collection::make($members)];
            }
        }

        return $groups;
    }

    public function render(): View
    {
        return view('people::guardians.duplicates', ['groups' => $this->groups()]);
    }
}
