<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\ToggleModuleData;
use Modules\Core\Domain\Events\Schools\ModuleDisabled;
use Modules\Core\Domain\Events\Schools\ModuleEnabled;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

/**
 * ACT-ToggleSchoolModule (Book A CORE-02 §3). BR-CORE-02-012: disabling a
 * module never deletes its data — re-enabling restores full access, since
 * this only ever flips `is_enabled` on the entitlement row.
 * BR-CORE-02-013: a module cannot be enabled while any of its declared
 * dependencies is disabled for the same school (`config('core.module_dependencies')`).
 */
final class ToggleSchoolModuleAction extends Action
{
    public function execute(ToggleModuleData $data): void
    {
        $school = School::query()->findOrFail($data->schoolId);

        if ($data->enable) {
            $this->assertDependenciesEnabled($school, $data->moduleCode);
        }

        $this->transaction(function () use ($school, $data): void {
            SchoolModule::updateOrCreate(
                ['school_id' => $school->id, 'module_code' => $data->moduleCode],
                [
                    'is_enabled' => $data->enable,
                    'enabled_at' => $data->enable ? now() : null,
                    'enabled_by' => $data->enable ? $data->actingUserId : null,
                    'expires_at' => $data->expiresAt,
                ],
            );

            event($data->enable
                ? new ModuleEnabled($school, $data->moduleCode)
                : new ModuleDisabled($school, $data->moduleCode));
        });
    }

    private function assertDependenciesEnabled(School $school, string $moduleCode): void
    {
        /** @var array<string, array<int, string>> $graph */
        $graph = config('core.module_dependencies', []);
        $dependencies = $graph[$moduleCode] ?? [];

        if ($dependencies === []) {
            return;
        }

        $enabled = SchoolModule::query()
            ->where('school_id', $school->id)
            ->where('is_enabled', true)
            ->pluck('module_code')
            ->all();

        $disabled = array_values(array_diff($dependencies, $enabled));

        if ($disabled !== []) {
            throw new InvalidStateTransitionException(
                "Cannot enable [{$moduleCode}]: dependency ".implode(', ', $disabled).' is not enabled.',
                ['module_code' => $moduleCode, 'disabled_dependencies' => $disabled],
            );
        }
    }
}
