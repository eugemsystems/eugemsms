<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Finance\Domain\Actions\HandleInboundGatewayWebhookAction;
use Modules\Finance\Domain\Exceptions\UnregisteredGatewayDriverException;

/**
 * `POST /api/v1/webhooks/payments/{driver}` — the gateway's result callback. Public by nature:
 * the driver authenticates the body itself (Pesepay: it must decrypt with the merchant key), and
 * an unauthenticated or unmatched body is recorded as failed and settles nothing. It always
 * answers 200 for a known driver so the gateway does not retry a body that will never verify.
 */
final class GatewayWebhookController
{
    public function receive(Request $request, string $driver, HandleInboundGatewayWebhookAction $action): JsonResponse
    {
        try {
            $webhook = $action->execute($driver, $request->headers->all(), $request->getContent());
        } catch (UnregisteredGatewayDriverException) {
            return ApiResponse::error('NOT_FOUND', 'That payment gateway is not recognised.', 404);
        }

        return ApiResponse::ok(['received' => true, 'processing_status' => $webhook->processing_status]);
    }
}
