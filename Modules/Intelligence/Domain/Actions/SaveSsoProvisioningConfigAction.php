<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\SsoProvisioningConfig;

/**
 * ACT-SaveSsoProvisioningConfig (Book J INT-04 §5, `integration.sso.manage`).
 * One row per school and provider. Credentials are write-only: a `null`
 * value keeps what is stored, and nothing ever reads them back out to a
 * screen. Auto-provisioning can only be switched on once credentials exist.
 */
final class SaveSsoProvisioningConfigAction extends Action
{
    public const array PROVIDERS = ['google_workspace', 'microsoft_365'];

    public function execute(int $schoolId, string $provider, string $domain, ?string $credentials, bool $autoProvisionStaff): SsoProvisioningConfig
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException("[{$provider}] is not a supported SSO provider.");
        }

        $domain = strtolower(trim($domain));

        if (preg_match('/^(?=.{4,120}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain) !== 1) {
            throw new InvalidArgumentException('Enter the school’s email domain, e.g. school.ac.zw.');
        }

        $existing = SsoProvisioningConfig::where('school_id', $schoolId)->where('provider', $provider)->first();
        $credentials = $credentials === null || trim($credentials) === '' ? null : $credentials;

        if ($credentials === null && $existing === null) {
            throw new InvalidArgumentException('Credentials are required the first time a provider is configured.');
        }

        return $this->transaction(fn (): SsoProvisioningConfig => SsoProvisioningConfig::updateOrCreate(
            ['school_id' => $schoolId, 'provider' => $provider],
            array_filter([
                'domain' => $domain,
                'credentials' => $credentials,
                'auto_provision_staff' => $autoProvisionStaff,
                'sync_status' => $existing === null ? 'pending' : $existing->sync_status,
            ], fn (mixed $value, string $key): bool => $key !== 'credentials' || $value !== null, ARRAY_FILTER_USE_BOTH),
        ));
    }
}
