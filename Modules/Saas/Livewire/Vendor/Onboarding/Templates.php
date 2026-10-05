<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Onboarding;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\DataObjects\Settings\ImportResult;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\ApplyOnboardingTemplateAction;
use Modules\Saas\Domain\Actions\CreateOnboardingTemplateAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\DataObjects\ApplyOnboardingTemplateData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\OnboardingTemplate;

/**
 * `Success\Onboarding\Templates` (Book J SAA-03 §5, vendor console). The
 * curated library of CORE-04 configuration profiles (BR-SAA-03-002 — no
 * second copy of configuration is stored here) and applying one to a
 * school. Applying is additive by default: existing settings and custom
 * fields are skipped unless overwrite is explicitly chosen, and the result
 * says exactly what was imported and what was skipped.
 */
#[Title('Template library')]
#[Layout('saas::layouts.vendor')]
final class Templates extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public ?int $profileId = null;

    public string $templateName = '';

    public string $suitedFor = '';

    public ?int $applyingId = null;

    public ?int $tenantId = null;

    public ?int $schoolId = null;

    public bool $overwriteSettings = false;

    public bool $overwriteCustomFields = false;

    public string $lastResult = '';

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function create(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['profileId' => ['required', 'integer'], 'templateName' => ['required', 'string', 'max:150'], 'suitedFor' => ['nullable', 'string', 'max:60']]);

        try {
            $template = app(CreateOnboardingTemplateAction::class)->execute((int) $this->profileId, $this->templateName, $this->suitedFor);
        } catch (InvalidArgumentException $exception) {
            $this->addError('profileId', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'template.created', "Template “{$template->template_name}” added to the library", null, ['template_id' => $template->id]);

        $this->reset('profileId', 'templateName', 'suitedFor');
        $this->toast(__('Template added.'));
    }

    public function beginApply(int $templateId): void
    {
        $this->authorizeVendor();

        $this->applyingId = OnboardingTemplate::query()->findOrFail($templateId)->id;
        $this->tenantId = null;
        $this->schoolId = null;
        $this->lastResult = '';
    }

    public function updatedTenantId(): void
    {
        $this->schoolId = null;
    }

    public function apply(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['tenantId' => ['required', 'integer'], 'schoolId' => ['required', 'integer']]);

        $template = OnboardingTemplate::query()->findOrFail($this->applyingId);
        $school = School::query()->where('tenant_id', $this->tenantId)->findOrFail($this->schoolId);

        $result = app(ApplyOnboardingTemplateAction::class)->execute(new ApplyOnboardingTemplateData(
            templateId: $template->id, targetSchoolId: $school->id, actingUserId: $operator->id,
            overwriteSettings: $this->overwriteSettings, overwriteCustomFields: $this->overwriteCustomFields,
        ));

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'template.applied', "Template “{$template->template_name}” applied to {$school->name}", $school->tenant_id, ['template_id' => $template->id, 'school_id' => $school->id, 'overwrite_settings' => $this->overwriteSettings, 'overwrite_custom_fields' => $this->overwriteCustomFields]);

        $this->lastResult = $this->describe($result);
        $this->applyingId = null;
        $this->toast(__('Template applied.'));
    }

    private function describe(ImportResult $result): string
    {
        return __(':sImported settings imported, :sSkipped skipped; :fImported custom fields imported, :fSkipped skipped.', [
            'sImported' => $result->settingsImported, 'sSkipped' => $result->settingsSkipped,
            'fImported' => $result->customFieldsImported, 'fSkipped' => $result->customFieldsSkipped,
        ]);
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $templates = OnboardingTemplate::query()->orderBy('template_name')->get();

        return view('saas::vendor.templates', [
            'templates' => $templates,
            'profileNames' => ConfigurationProfile::query()->withoutGlobalScopes()->whereIn('id', $templates->pluck('configuration_profile_id'))->pluck('name', 'id'),
            'profiles' => ConfigurationProfile::query()->withoutGlobalScopes()->orderBy('name')->limit(300)->get(['id', 'name']),
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'schools' => $this->tenantId === null ? collect() : School::query()->where('tenant_id', $this->tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
