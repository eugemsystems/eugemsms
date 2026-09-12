<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Roles;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\CloneRoleTemplateAction;
use Modules\Core\Domain\DataObjects\Auth\CloneRoleData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * `Core\Roles\Index` (Book A CORE-05 §6, `core.role.view`). Lists every
 * role usable in this school: the school's own roles plus every system
 * template (`school_id = null`), which — per BR-CORE-05-013 — is visible
 * to every school but can only be edited via a clone. A school-owned
 * role links straight to `Core\Roles\Editor`; a system template offers
 * "Clone" instead of "Edit", since editing one directly throws
 * `SystemRoleTemplateException`.
 */
#[Title('Roles')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCloneModal = false;

    public ?int $cloningRoleId = null;

    public string $cloningRoleName = '';

    public string $newName = '';

    public string $newDisplayName = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function openCloneModal(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        $this->cloningRoleId = $role->id;
        $this->cloningRoleName = $role->display_name;
        $this->newName = '';
        $this->newDisplayName = $role->display_name.' (Copy)';
        $this->showCloneModal = true;
        $this->resetErrorBag();
    }

    public function cloneRole(): void
    {
        $this->validate([
            'newName' => ['required', 'string', 'max:80'],
            'newDisplayName' => ['required', 'string', 'max:120'],
        ]);

        try {
            $clone = app(CloneRoleTemplateAction::class)->execute(new CloneRoleData(
                sourceRoleId: (int) $this->cloningRoleId,
                schoolId: $this->school->id,
                name: $this->newName,
                displayName: $this->newDisplayName,
                clonedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'danger');

            return;
        }

        $this->reset(['showCloneModal', 'cloningRoleId', 'cloningRoleName', 'newName', 'newDisplayName']);

        $this->toast(__('Role cloned — edit its permissions below.'));

        $this->redirect(route('roles.edit', [$this->school, $clone]), navigate: true);
    }

    public function render(): View
    {
        $query = Role::query()->where(function (Builder $query): void {
            $query->where('school_id', $this->school->id)->orWhereNull('school_id');
        });

        return view('core::roles.index', [
            'roles' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'display_name' => ['label' => __('Role'), 'column' => 'display_name', 'sortable' => true, 'searchable' => true],
            'category' => ['label' => __('Category'), 'sortable' => true, 'filter' => 'select', 'options' => $this->categoryOptions()],
            'is_system' => [
                'label' => __('Type'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('System template'), '0' => __('School role')],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return Role::query()
            ->where(function (Builder $query): void {
                $query->where('school_id', $this->school->id)->orWhereNull('school_id');
            })
            ->distinct()
            ->orderBy('category')
            ->pluck('category', 'category')
            ->all();
    }
}
