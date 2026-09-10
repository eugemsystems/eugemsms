<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Files;

use Illuminate\Support\Carbon;

/**
 * Book A CORE-10 BR-CORE-10-003. Files are served exclusively through
 * signed URLs, default TTL 5 minutes. A self-contained HMAC scheme
 * rather than Laravel's `URL::temporarySignedRoute()` — that needs a
 * real named route, which belongs to the API wave (not built yet);
 * this generator and its `verify()` are exactly what that future
 * route's controller will call, unchanged, once it exists.
 */
final class SignedFileUrlGenerator
{
    public function __construct(
        private readonly string $appKey,
    ) {}

    public function generate(string $fileUlid, string $variant, int $ttlMinutes = 5): string
    {
        $expires = (int) Carbon::now()->addMinutes($ttlMinutes)->timestamp;
        $signature = $this->sign($fileUlid, $variant, $expires);

        return "/files/{$fileUlid}/download?variant={$variant}&expires={$expires}&signature={$signature}";
    }

    public function verify(string $fileUlid, string $variant, int $expires, string $signature): bool
    {
        if ($expires < Carbon::now()->timestamp) {
            return false;
        }

        return hash_equals($this->sign($fileUlid, $variant, $expires), $signature);
    }

    private function sign(string $fileUlid, string $variant, int $expires): string
    {
        return hash_hmac('sha256', "{$fileUlid}|{$variant}|{$expires}", $this->appKey);
    }
}
