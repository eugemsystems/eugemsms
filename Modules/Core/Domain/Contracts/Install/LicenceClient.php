<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

/**
 * The licence server is a SAA-01 (vendor-side) concern that doesn't exist
 * yet — treated as an isolated adapter behind an interface per the
 * pattern FIN-13 uses for ZIMRA (Book A CORE-01 risk note equivalent).
 * The default `HttpLicenceClient` implementation always reports
 * `unreachable` when no server URL is configured, which correctly drives
 * `ActivateLicenceAction` into the 14-day grace path (BR-CORE-01-007).
 */
interface LicenceClient
{
    public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse;
}
