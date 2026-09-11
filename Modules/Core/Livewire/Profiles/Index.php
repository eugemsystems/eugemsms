<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Profiles;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Settings\ExportConfigurationProfileAction;
use Modules\Core\Domain\Actions\Settings\ImportConfigurationProfileAction;
use Modules\Core\Domain\DataObjects\Settings\ExportConfigurationProfileData;
use Modules\Core\Domain\DataObjects\Settings\ImportConfigurationProfileData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\School;

/**
 * `Core\Profiles\Index` (Book A CORE-04 §5). Export this school's
 * settings/custom fields as a reusable profile, or import one of the
 * tenant's existing profiles into this school — BR-CORE-04-014/015:
 * export never carries learner/staff/financial data or encrypted
 * settings, import is additive unless the overwrite flags are set.
 */
#[Title('Configuration profiles')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public bool $showExportModal = false;

    public string $exportName = '';

    public string $exportDescription = '';

    public ?int $importingProfileId = null;

    public bool $overwriteSettings = false;

    public bool $overwriteCustomFields = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function export(): void
    {
        try {
            app(ExportConfigurationProfileAction::class)->execute(new ExportConfigurationProfileData(
                tenantId: $this->school->tenant_id,
                sourceSchoolId: $this->school->id,
                name: $this->exportName,
                actingUserId: (int) Auth::id(),
                description: $this->exportDescription === '' ? null : $this->exportDescription,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['showExportModal', 'exportName', 'exportDescription']);

        $this->toast(__('Profile exported.'));
    }

    public function openImportModal(int $profileId): void
    {
        $this->importingProfileId = $profileId;
        $this->reset(['overwriteSettings', 'overwriteCustomFields']);
    }

    public function import(): void
    {
        if ($this->importingProfileId === null) {
            return;
        }

        try {
            $result = app(ImportConfigurationProfileAction::class)->execute(new ImportConfigurationProfileData(
                profileId: $this->importingProfileId,
                targetSchoolId: $this->school->id,
                actingUserId: (int) Auth::id(),
                overwriteSettings: $this->overwriteSettings,
                overwriteCustomFields: $this->overwriteCustomFields,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->importingProfileId = null;

        $this->toast(__(':settingsImported settings and :fieldsImported custom fields imported (:settingsSkipped / :fieldsSkipped skipped as already set).', [
            'settingsImported' => $result->settingsImported,
            'fieldsImported' => $result->customFieldsImported,
            'settingsSkipped' => $result->settingsSkipped,
            'fieldsSkipped' => $result->customFieldsSkipped,
        ]));
    }

    public function render(): View
    {
        return view('core::profiles.index', [
            'profiles' => ConfigurationProfile::where('tenant_id', $this->school->tenant_id)
                ->with(['sourceSchool', 'creator'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }
}
