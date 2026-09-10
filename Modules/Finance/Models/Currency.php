<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Book B FIN-06 §2. Seeded, global. Not to be confused with
 * `Modules\Core\Domain\Support\Currency`, the closed-set enum `Money`
 * is built against — this is the richer, DB-backed display/formatting
 * config for that same set.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $minor_unit_digits
 * @property string $display_format
 * @property bool $is_active
 * @property int $sort_order
 */
class Currency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'symbol', 'minor_unit_digits', 'display_format', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function format(int $amountMinor): string
    {
        $amount = number_format($amountMinor / (10 ** $this->minor_unit_digits), $this->minor_unit_digits);

        return str_replace(['{symbol}', '{amount}'], [$this->symbol, $amount], $this->display_format);
    }
}
