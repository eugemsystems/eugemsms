<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\ModuleEntitlement;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\ToggleSchoolModuleAction;
use Modules\Core\Domain\DataObjects\Schools\ToggleModuleData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

/**
 * `Core\Modules\Index` (Book A CORE-02 §5). Named `ModuleEntitlement`
 * here to avoid a `Modules\Modules` namespace. BR-CORE-02-013: enabling
 * is refused while a declared dependency is disabled, surfaced as a
 * toast rather than a silent no-op.
 */
#[Title('Modules')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function toggle(string $moduleCode, bool $enable): void
    {
        try {
            app(ToggleSchoolModuleAction::class)->execute(new ToggleModuleData(
                schoolId: $this->school->id,
                moduleCode: $moduleCode,
                enable: $enable,
                actingUserId: (int) Auth::id(),
            ));

            $this->toast($enable ? __('Module enabled.') : __('Module disabled.'));
        } catch (InvalidStateTransitionException $exception) {
            $this->toast($exception->getMessage(), 'danger');
        }
    }

    public function render(): View
    {
        $known = array_keys(config('core.module_dependencies', []));
        $entitled = SchoolModule::query()->where('school_id', $this->school->id)->pluck('module_code')->all();

        $codes = collect($known)->merge($entitled)->push('CORE')->unique()->sort()->values();

        $entitlements = SchoolModule::query()
            ->where('school_id', $this->school->id)
            ->get()
            ->keyBy('module_code');

        return view('core::module-entitlement.index', [
            'modules' => $codes->map(function (string $code) use ($entitlements): array {
                $entitlement = $entitlements->get($code);

                return [
                    'code' => $code,
                    'isEnabled' => $entitlement !== null && $entitlement->is_enabled,
                    'dependencies' => config("core.module_dependencies.{$code}", []),
                ];
            }),
        ]);
    }
}
