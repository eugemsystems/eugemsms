<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Populates the `ulid` column every business table carries alongside its
 * auto-increment integer primary key (Book A §0.3/§0.4). Unlike Laravel's
 * built-in `HasUlids`, this does not replace the primary key — the ULID is
 * a second, public-facing identifier: exposed in APIs and URLs, never the
 * integer ID (BR-GLOBAL-040).
 *
 * @mixin Model
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', (string) Str::ulid());
            }
        });
    }
}
