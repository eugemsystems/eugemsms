<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Establishment;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateDepartmentAction;
use Modules\People\Domain\Actions\CreateEstablishmentPostAction;
use Modules\People\Domain\Actions\FillEstablishmentPostAction;
use Modules\People\Domain\Actions\VacateEstablishmentPostAction;
use Modules\People\Domain\DataObjects\CreateDepartmentData;
use Modules\People\Domain\DataObjects\CreateEstablishmentPostData;
use Modules\People\Domain\DataObjects\FillEstablishmentPostData;
use Modules\People\Models\Department;
use Modules\People\Models\EstablishmentPost;

/**
 * `People\Establishment\Index` (Book C PPL-04 §5/BR-PPL-04-004,
 * `people.staff.establishment_manage`). Create-only for both
 * departments and posts — no `Update*Action` exists for either.
 * Overriding a full post's capacity is offered here rather than only
 * at staff-creation time, since a head may want to approve the
 * overstaffing before, not just incidentally while hiring.
 */
#[Title('Establishment')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $departmentCode = '';

    public string $departmentName = '';

    public string $departmentType = 'academic';

    public ?int $departmentParentId = null;

    public string $postTitle = '';

    public ?int $postDepartmentId = null;

    public string $postApprovedCount = '1';

    public bool $postIsTeaching = false;

    public bool $overrideEstablishment = false;

    public string $overrideReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.establishment_manage');
    }

    public function createDepartment(): void
    {
        $this->validate([
            'departmentCode' => ['required', 'string', 'max:20'],
            'departmentName' => ['required', 'string', 'max:120'],
            'departmentType' => ['required', 'in:academic,administrative,operational'],
        ]);

        app(CreateDepartmentAction::class)->execute(new CreateDepartmentData(
            schoolId: $this->school->id,
            code: $this->departmentCode,
            name: $this->departmentName,
            type: $this->departmentType,
            parentId: $this->departmentParentId,
        ));

        $this->reset(['departmentCode', 'departmentName', 'departmentParentId']);
        $this->toast(__('Department created.'));
    }

    public function createPost(): void
    {
        $this->validate([
            'postTitle' => ['required', 'string', 'max:120'],
            'postApprovedCount' => ['required', 'integer', 'min:1'],
        ]);

        app(CreateEstablishmentPostAction::class)->execute(new CreateEstablishmentPostData(
            schoolId: $this->school->id,
            title: $this->postTitle,
            departmentId: $this->postDepartmentId,
            approvedCount: (int) $this->postApprovedCount,
            isTeaching: $this->postIsTeaching,
        ));

        $this->reset(['postTitle', 'postDepartmentId', 'postApprovedCount', 'postIsTeaching']);
        $this->postApprovedCount = '1';
        $this->toast(__('Post created.'));
    }

    public function fillPost(int $postId): void
    {
        try {
            app(FillEstablishmentPostAction::class)->execute(new FillEstablishmentPostData(
                postId: $postId,
                overrideEstablishment: $this->overrideEstablishment,
                overrideReason: $this->overrideEstablishment ? $this->overrideReason : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Post marked filled.'));
    }

    public function vacate(int $postId): void
    {
        app(VacateEstablishmentPostAction::class)->execute($postId);
        $this->toast(__('Post marked vacant.'));
    }

    public function render(): View
    {
        return view('people::establishment.index', [
            'departments' => Department::where('school_id', $this->school->id)->orderBy('name')->get(),
            'posts' => EstablishmentPost::where('school_id', $this->school->id)->with('department')->orderBy('title')->get(),
        ]);
    }
}
