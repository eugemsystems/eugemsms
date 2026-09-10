<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Wallet\Domain\DataObjects\CreateWalletProductData;
use Modules\Wallet\Models\WalletProduct;

/**
 * ACT-CreateWalletProduct (Book H3 FIN-14 §2).
 */
final class CreateWalletProductAction extends Action
{
    public function execute(CreateWalletProductData $data): WalletProduct
    {
        return $this->transaction(fn (): WalletProduct => WalletProduct::create([
            'school_id' => $data->schoolId,
            'spend_point_id' => $data->spendPointId,
            'item_id' => $data->itemId,
            'code' => $data->code,
            'name' => $data->name,
            'category' => $data->category,
            'price_minor' => $data->priceMinor,
            'currency' => $data->currency,
            'tax_type' => $data->taxType,
            'barcode' => $data->barcode,
            'is_active' => true,
        ]));
    }
}
