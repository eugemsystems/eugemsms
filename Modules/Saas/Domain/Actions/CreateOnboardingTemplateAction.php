<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Saas\Models\OnboardingTemplate;

/**
 * ACT-CreateOnboardingTemplate (Book J SAA-03 §2/BR-SAA-03-002). Names an
 * existing CORE-04 configuration profile as a library template — it stores
 * no copy of any configuration, only the profile's id, a curated name and a
 * suitability tag. One library entry per profile.
 */
final class CreateOnboardingTemplateAction extends Action
{
    public function execute(int $configurationProfileId, string $templateName, ?string $suitedFor = null): OnboardingTemplate
    {
        if (trim($templateName) === '' || mb_strlen($templateName) > 150 || ($suitedFor !== null && mb_strlen($suitedFor) > 60)) {
            throw new InvalidArgumentException('A template needs a name of up to 150 characters; the suitability tag is at most 60.');
        }

        $profile = ConfigurationProfile::query()->findOrFail($configurationProfileId);

        if (OnboardingTemplate::query()->where('configuration_profile_id', $profile->id)->exists()) {
            throw new InvalidArgumentException('That configuration profile is already in the library.');
        }

        return $this->transaction(fn (): OnboardingTemplate => OnboardingTemplate::create([
            'configuration_profile_id' => $profile->id,
            'template_name' => trim($templateName),
            'suited_for' => $suitedFor === null || trim($suitedFor) === '' ? null : trim($suitedFor),
            'used_count' => 0,
        ]));
    }
}
