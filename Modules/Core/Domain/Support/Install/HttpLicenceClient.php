<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Core\Domain\Contracts\Install\LicenceClient;
use Modules\Core\Domain\Contracts\Install\LicenceServerResponse;
use Throwable;

final class HttpLicenceClient implements LicenceClient
{
    public function activate(string $licenceKey, string $installationUuid): LicenceServerResponse
    {
        $url = config('services.serp_licence.url');

        if (! is_string($url) || $url === '') {
            return new LicenceServerResponse(reachable: false, valid: false, message: 'No licence server is configured.');
        }

        try {
            $response = Http::timeout(5)->post($url.'/activate', [
                'licence_key' => $licenceKey,
                'installation_uuid' => $installationUuid,
            ]);

            if (! $response->successful()) {
                return new LicenceServerResponse(reachable: true, valid: false, message: $response->json('message', 'The licence server rejected this key.'));
            }

            return new LicenceServerResponse(
                reachable: true,
                valid: (bool) $response->json('valid', false),
                expiresAt: $response->json('expires_at') !== null ? Carbon::parse($response->json('expires_at')) : null,
                message: $response->json('message'),
            );
        } catch (Throwable $exception) {
            return new LicenceServerResponse(reachable: false, valid: false, message: $exception->getMessage());
        }
    }
}
