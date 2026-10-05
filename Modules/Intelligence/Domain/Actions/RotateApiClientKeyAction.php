<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\ApiClient;

/**
 * ACT-RotateApiClientKey (Book J INT-04 §5, "credential rotation"). Replaces
 * the stored hash with a fresh key, so the old one stops working at once;
 * the new plaintext is returned once and never stored (BR-INT-04-002). A
 * revoked client cannot be revived by rotating it.
 */
final class RotateApiClientKeyAction extends Action
{
    /**
     * @return array{client: ApiClient, plaintextKey: string}
     */
    public function execute(int $clientId): array
    {
        $client = ApiClient::findOrFail($clientId);

        if (! $client->is_active) {
            throw new InvalidArgumentException('A revoked client cannot be rotated — issue a new one.');
        }

        $plaintextKey = Str::random(48);

        $this->transaction(fn () => $client->update(['api_key_hash' => Hash::make($plaintextKey)]));

        return ['client' => $client->fresh(), 'plaintextKey' => $plaintextKey];
    }
}
