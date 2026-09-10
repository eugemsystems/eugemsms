<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\Events\WhatsAppQualityDegraded;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-UpdateWhatsAppQualityRating (Book I COM-01 §3 ⭐/BR-COM-01-007
 * (AC-COM-01-004)). Meta reports quality rating via webhook or the
 * Graph API — this just records what Meta reports. The actual pause
 * enforcement lives in `FakeWhatsAppGatewayDriver::send()`, which
 * reads the SAME configurable threshold this compares against, so the
 * two can never disagree about what "paused" means.
 */
final class UpdateWhatsAppQualityRatingAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $wabaId, string $qualityRating): WhatsAppBusinessAccount
    {
        return $this->transaction(function () use ($wabaId, $qualityRating): WhatsAppBusinessAccount {
            $account = WhatsAppBusinessAccount::findOrFail($wabaId);
            $previousRating = $account->quality_rating ?? 'green';
            $threshold = (string) $this->settings->get('comms.whatsapp_quality_pause_threshold', new ScopeChain(schoolId: $account->school_id));

            $account->update(['quality_rating' => $qualityRating]);

            if ($qualityRating === $threshold && $previousRating !== $threshold) {
                event(new WhatsAppQualityDegraded($account, $previousRating));
            }

            return $account;
        });
    }
}
