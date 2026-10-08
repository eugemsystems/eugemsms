<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Events\ApiClientIssued;
use Modules\Intelligence\Domain\Registry\HardwareScanRouteRegistry;
use Modules\Intelligence\Models\ApiClient;

/**
 * ACT-IssueApiClient (Book J INT-04 §2/BR-INT-04-001/002). Refuses at
 * issuance, not silently narrows, when a requested ability isn't on
 * the real allow-list (BR-INT-04-001) — currently `{purpose}:write`
 * for every purpose `HardwareScanRouteRegistry` actually knows how to
 * route, plus `usage:read` (reading a client's own `api_usage_log`)
 * and `reports:read` (Book J INT-01 §5's `/api/v1/reports/*`, added
 * once that surface existed to scope an ability to). Expanding this
 * list is future work as more of the public API is actually built,
 * not a placeholder abstraction now.
 *
 * The plaintext key is returned once, here, and never stored — only
 * `api_key_hash` persists (BR-INT-04-002).
 */
final class IssueApiClientAction extends Action
{
    private const array GENERIC_ABILITIES = ['usage:read', 'reports:read'];

    /**
     * @param  array<int, string>  $scopedAbilities
     * @param  array<int, string>|null  $ipAllowlist
     * @return array{client: ApiClient, plaintextKey: string}
     */
    public function execute(
        int $schoolId,
        string $name,
        string $clientType,
        array $scopedAbilities,
        ?string $contactEmail = null,
        ?array $ipAllowlist = null,
        int $rateLimitPerMinute = 60,
        ?int $createdByUserId = null,
    ): array {
        if (trim($name) === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('A client needs a name of up to 150 characters.');
        }

        if (! in_array($clientType, ['integration', 'hardware_device'], true)) {
            throw new InvalidArgumentException("[{$clientType}] is not a client type.");
        }

        if ($scopedAbilities === []) {
            throw new InvalidArgumentException('A client must be scoped to at least one ability.');
        }

        if ($rateLimitPerMinute < 1 || $rateLimitPerMinute > 10000) {
            throw new InvalidArgumentException('The rate limit must be between 1 and 10,000 requests per minute.');
        }

        foreach ($ipAllowlist ?? [] as $entry) {
            if (! self::isIpOrCidr($entry)) {
                throw new InvalidArgumentException("[{$entry}] is not an IP address or CIDR range.");
            }
        }

        $validAbilities = [...self::GENERIC_ABILITIES, ...HardwareScanRouteRegistry::abilities()];
        $unlisted = array_diff($scopedAbilities, $validAbilities);

        if ($unlisted !== []) {
            throw new InvalidArgumentException('Requested abilities are not on the allow-list: '.implode(', ', $unlisted));
        }

        // Bearer format `{client ulid}.{secret}`: the ulid lets the API-client middleware find the
        // row to verify the bcrypt hash against (a hash alone cannot be looked up).
        $ulid = (string) Str::ulid();
        $plaintextKey = $ulid.'.'.Str::random(40);

        $client = $this->transaction(fn (): ApiClient => ApiClient::create([
            'ulid' => $ulid,
            'school_id' => $schoolId,
            'name' => $name,
            'client_type' => $clientType,
            'contact_email' => $contactEmail,
            'api_key_hash' => Hash::make($plaintextKey),
            'scoped_abilities' => $scopedAbilities,
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'ip_allowlist' => $ipAllowlist,
            'is_active' => true,
            'created_by' => $createdByUserId,
        ]));

        event(new ApiClientIssued($client));

        return ['client' => $client, 'plaintextKey' => $plaintextKey];
    }

    private static function isIpOrCidr(string $entry): bool
    {
        [$address, $prefix] = array_pad(explode('/', $entry, 2), 2, null);

        $isV4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        if (! $isV4 && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        return $prefix === null || (ctype_digit($prefix) && (int) $prefix <= ($isV4 ? 32 : 128));
    }
}
