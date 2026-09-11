<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

/**
 * The licence server is a SAA-01 (vendor-side) concern — treated as an
 * isolated adapter behind an interface per the pattern FIN-13 uses for
 * ZIMRA (Book A CORE-01 risk note equivalent). The default
 * `HttpLicenceClient` implementation always reports `unreachable` when
 * no server URL is configured, which correctly drives
 * `ActivateLicenceAction` into the 14-day grace path (BR-CORE-01-007)
 * and `Modules\Saas\Domain\Actions\ValidateLicenceKeyAction` into its
 * own `offline_grace_days` fallback (Book J SAA-01 §4/BR-SAA-01-007).
 */
interface LicenceClient
{
    public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse;

    /**
     * The recurring re-check `licence_keys.last_validated_at` records —
     * same server, same reachability contract as `activate()`, just
     * asking "is this still good" instead of "accept this key".
     */
    public function validate(string $licenceKey, string $installationUuid): LicenceServerResponse;
}
