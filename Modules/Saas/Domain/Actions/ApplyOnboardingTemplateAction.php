<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Settings\ImportConfigurationProfileAction;
use Modules\Core\Domain\DataObjects\Settings\ImportConfigurationProfileData;
use Modules\Core\Domain\DataObjects\Settings\ImportResult;
use Modules\Saas\Domain\DataObjects\ApplyOnboardingTemplateData;
use Modules\Saas\Models\OnboardingTemplate;

/**
 * ACT-ApplyOnboardingTemplate (Book J SAA-03 §4/BR-SAA-03-002). Clones
 * the template's underlying `CORE-04` `ConfigurationProfile` onto the
 * target school via the existing `ImportConfigurationProfileAction` —
 * this module owns no second import mechanism — and increments the
 * template's own `used_count`.
 */
final class ApplyOnboardingTemplateAction extends Action
{
    public function __construct(
        private readonly ImportConfigurationProfileAction $importProfile,
    ) {}

    public function execute(ApplyOnboardingTemplateData $data): ImportResult
    {
        $template = OnboardingTemplate::query()->findOrFail($data->templateId);

        return $this->transaction(function () use ($template, $data): ImportResult {
            $result = $this->importProfile->execute(new ImportConfigurationProfileData(
                profileId: $template->configuration_profile_id,
                targetSchoolId: $data->targetSchoolId,
                actingUserId: $data->actingUserId,
                overwriteSettings: $data->overwriteSettings,
                overwriteCustomFields: $data->overwriteCustomFields,
            ));

            $template->increment('used_count');

            return $result;
        });
    }
}
