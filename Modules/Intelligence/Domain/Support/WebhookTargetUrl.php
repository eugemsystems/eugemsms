<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Support;

use InvalidArgumentException;

/**
 * Book J INT-04 §2. A webhook target is a URL a school types in and the
 * server then calls, so it is a server-side request forgery vector:
 * without this, a subscription pointed at `http://169.254.169.254/…`
 * or an internal service would make the platform fetch it. Only
 * `https` is allowed, and neither a literal IP nor any address the host
 * resolves to may be private, loopback, link-local or reserved. A host
 * that does not resolve at all is let through — the delivery itself
 * would fail to connect — but this check runs again at every dispatch,
 * so a name that later resolves internally is still refused.
 */
final class WebhookTargetUrl
{
    /**
     * @throws InvalidArgumentException
     */
    public static function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === null || $host === '') {
            throw new InvalidArgumentException('A webhook target must be a valid https:// URL.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('A webhook target URL must not embed credentials.');
        }

        $host = trim($host, '[]');

        if (strcasecmp($host, 'localhost') === 0 || str_ends_with(strtolower($host), '.localhost') || str_ends_with(strtolower($host), '.internal')) {
            throw new InvalidArgumentException('A webhook target must be a public host.');
        }

        foreach (self::addressesFor($host) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('A webhook target must resolve to a public address.');
            }
        }
    }

    public static function isSafe(string $url): bool
    {
        try {
            self::assertSafe($url);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private static function addressesFor(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
