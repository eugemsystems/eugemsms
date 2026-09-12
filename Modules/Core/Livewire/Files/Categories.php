<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Files;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\FileCategory;
use Modules\Core\Models\School;

/**
 * `Core\Files\Categories` (Book A CORE-10 §2, `core.file.view`) — a
 * read-only reference of every registered file category (MIME/size
 * limits, sensitivity, variant generation, expiry requirement). Code
 * owns the list via `FileCategoryRegistry`; this reads the DB mirror
 * `syncToDatabase()` keeps current, the same split `Audit\Integrity`
 * uses for `IntegrityCheckRegistry`. No create/edit here — a category
 * is defined by the module that owns it, not by an admin.
 */
#[Title('File categories')]
#[Layout('layouts.app')]
final class Categories extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.file.view');
    }

    public function render(): View
    {
        return view('core::files.categories', [
            'categories' => FileCategory::query()->orderBy('label')->get(),
        ]);
    }
}
