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
 * route, plus `usage:read` (the one generic, already-real capability
 * this pass builds — reading a client's own `api_usage_log`). No
 * other third-party REST surface exists yet in this codebase to scope
 * an ability to; expanding this list is future work as more of the
 * public API is actually built, not a placeholder abstraction now.
 *
 * The plaintext key is returned once, here, and never stored — only
 * `api_key_hash` persists (BR-INT-04-002).
 */
final class IssueApiClientAction extends Action
{
    private const array GENERIC_ABILITIES = ['usage:read'];

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
        $validAbilities = [...self::GENERIC_ABILITIES, ...HardwareScanRouteRegistry::abilities()];
        $unlisted = array_diff($scopedAbilities, $validAbilities);

        if ($unlisted !== []) {
            throw new InvalidArgumentException('Requested abilities are not on the allow-list: '.implode(', ', $unlisted));
        }

        $plaintextKey = Str::random(48);

        $client = $this->transaction(fn (): ApiClient => ApiClient::create([
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
}
