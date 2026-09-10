<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Events\ApiClientRevoked;
use Modules\Intelligence\Models\ApiClient;

final class RevokeApiClientAction extends Action
{
    public function execute(int $clientId, int $revokedByUserId): ApiClient
    {
        $client = ApiClient::findOrFail($clientId);

        return $this->transaction(function () use ($client, $revokedByUserId): ApiClient {
            $client->update(['is_active' => false, 'revoked_at' => Carbon::now(), 'revoked_by' => $revokedByUserId]);

            event(new ApiClientRevoked($client));

            return $client->fresh();
        });
    }
}
