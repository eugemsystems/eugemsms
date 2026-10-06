<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Scheduling\ResolveSystemActorAction;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\DataObjects\IngestGatewayWebhookData;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\GatewayWebhook;
use Modules\Finance\Models\PaymentIntent;
use Throwable;

/**
 * ACT-HandleInboundGatewayWebhook (Book B FIN-05 §4, BR-FIN-05-004/006). The public callback
 * endpoint has no signed-in user and no school, but ingesting needs both. This finds the school
 * from the gateway's own reference — only once the driver has authenticated the body — sets it as
 * the context, resolves that school's credit-balance and suspense accounts, and then hands over to
 * `IngestGatewayWebhookAction`, which owns replay protection, the signature record and settlement.
 * A body that fails verification, or names no known payment, is still ingested (and recorded as
 * failed) with no school, so a forged callback leaves a trace and settles nothing.
 */
final class HandleInboundGatewayWebhookAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
        private readonly IngestGatewayWebhookAction $ingest,
        private readonly ResolveSystemActorAction $systemActor,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     */
    public function execute(string $driverKey, array $headers, string $body): GatewayWebhook
    {
        $driver = $this->drivers->resolve($driverKey);
        $creditBalanceAccountId = null;
        $suspenseAccountId = null;

        if ($driver->verifyWebhook($headers, $body)) {
            try {
                $reference = $driver->parseWebhook($headers, $body)->gatewayReference;
            } catch (Throwable) {
                $reference = null;
            }

            $intent = $reference === null ? null : PaymentIntent::withoutGlobalScopes()->where('gateway_reference', $reference)->first();
            $school = $intent === null ? null : School::query()->find($intent->school_id);

            if ($school !== null) {
                SchoolContext::set($school);
                $creditBalanceAccountId = Account::query()->where('system_key', 'credit_balance')->where('is_active', true)->value('id');
                $suspenseAccountId = Account::query()->where('system_key', 'suspense')->where('is_active', true)->value('id');
            }
        }

        return $this->ingest->execute(new IngestGatewayWebhookData(
            driver: $driverKey,
            headers: $headers,
            body: $body,
            processedByUserId: $this->systemActor->execute(),
            creditBalanceAccountId: $creditBalanceAccountId !== null ? (int) $creditBalanceAccountId : null,
            suspenseAccountId: $suspenseAccountId !== null ? (int) $suspenseAccountId : null,
        ));
    }
}
