<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\Actions\Schools\CreateSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Domain\DataObjects\Schools\CreateSchoolData;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Models\School;

/**
 * `Core\Schools\Index` (Book A CORE-02 §5). The schools the current user
 * is assigned to, with a create-school modal. `core.school.view`/
 * `core.school.create` are not yet enforced (CORE-05).
 */
#[Title('Schools')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithDataTable;
    use Toasts;

    public bool $showCreateModal = false;

    public string $code = '';

    public string $name = '';

    public string $category = 'private';

    public function create(): void
    {
        $user = Auth::user();
        $referenceSchool = $user?->primarySchool() ?? $user?->schools()->first();
        $tenantId = $referenceSchool?->tenant_id;

        if ($tenantId === null) {
            $this->addError('code', __('You have no tenant to create a school under yet.'));

            return;
        }

        $userId = (int) Auth::id();

        $school = app(CreateSchoolAction::class)->execute(new CreateSchoolData(
            tenantId: $tenantId,
            code: $this->code,
            name: $this->name,
            category: $this->category,
            actingUserId: $userId,
        ));

        app(AssignUserToSchoolAction::class)->execute(new AssignUserData(
            schoolId: $school->id,
            userId: $userId,
            assignedByUserId: $userId,
        ));

        $this->reset(['code', 'name', 'showCreateModal']);
        $this->category = 'private';

        $this->toast(__('School created.'));
    }

    public function render(): View
    {
        $schoolIds = Auth::user()?->schools()->pluck('schools.id') ?? collect();

        $query = School::query()->whereIn('id', $schoolIds)->orderBy('name');

        return view('core::schools.index', [
            'schools' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'code' => ['label' => __('Code'), 'sortable' => true, 'searchable' => true],
            'name' => ['label' => __('Name'), 'sortable' => true, 'searchable' => true],
            'category' => [
                'label' => __('Category'), 'sortable' => true, 'filter' => 'select',
                'options' => [
                    'government' => __('Government'), 'council' => __('Council'), 'mission' => __('Mission'),
                    'trust' => __('Trust'), 'private' => __('Private'),
                ],
            ],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['active' => __('Active'), 'archived' => __('Archived')],
            ],
        ];
    }
}
