<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Imports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Imports\GenerateImportTemplateAction;
use Modules\Core\Domain\DataObjects\Imports\GenerateImportTemplateData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\ImportDefinition;
use Modules\Core\Models\School;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Core\Import\Index` (Book A CORE-11 §5, `core.import.view`) — the
 * import centre: every registered import, its unmet dependencies
 * (AC-CORE-11-005), and whether the current user holds that specific
 * import's own `required_permission` (declared per-definition by the
 * owning module, not a CORE-11-wide permission — the same reasoning as
 * `Files\Index`'s `core.file.view_sensitive` layered on a baseline).
 *
 * No concrete `Importer` is registered by any module yet (this is the
 * framework + UI wave; entity importers are each owning module's own
 * later work per this module's own "out of scope" note) — an empty
 * list here is correct, not a bug, the same as `NotificationKeyRegistry`
 * being empty before any module registered a key.
 *
 * The spec's separate `Core\Import\Template` screen is folded into this
 * one's `downloadTemplate()` action — generating a template is a single
 * CSV response with no state of its own worth a dedicated page for.
 */
#[Title('Import centre')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.import.view');
    }

    public function downloadTemplate(string $definitionKey): Response
    {
        $csv = app(GenerateImportTemplateAction::class)->execute(new GenerateImportTemplateData($definitionKey));

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$definitionKey}-template.csv\"",
        ]);
    }

    public function canStart(ImportDefinition $definition): bool
    {
        return $this->unmetDependencies($definition) === [] && $this->hasPermission($definition->required_permission);
    }

    /**
     * @return array<int, string>
     */
    public function unmetDependencies(ImportDefinition $definition): array
    {
        $completed = ImportBatch::query()
            ->where('school_id', $this->school->id)
            ->where('status', 'completed')
            ->whereIn('definition_key', $definition->depends_on ?? [])
            ->pluck('definition_key')
            ->all();

        return array_values(array_diff($definition->depends_on ?? [], $completed));
    }

    private function hasPermission(string $name): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, $name, PermissionScope::Own, $this->school->id);
    }

    public function render(): View
    {
        $definitions = ImportDefinition::query()->orderBy('sort_order')->orderBy('label')->get();

        return view('core::imports.index', [
            'definitions' => $definitions,
            'definitionLabels' => $definitions->pluck('label', 'key'),
        ]);
    }
}
