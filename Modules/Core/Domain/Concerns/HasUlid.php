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
 * `getRouteKeyName()` (2026-09-12 — user-reported: the id was still what
 * every URL actually showed, e.g. `/users/5`, despite BR-GLOBAL-040
 * already existing and every applicable table already carrying this
 * column) is the piece that was missing to make that rule real: it's
 * what Eloquent's implicit route-model binding AND `UrlGenerator`'s
 * route-key resolution both consult, so adding it here — once — makes
 * every `Route::livewire('x/{model}', ...)` definition and every
 * `route(...)`/`redirect()->route(...)` call that passes a MODEL
 * INSTANCE (rather than a raw `->id`) resolve/generate by `ulid`
 * automatically, for every one of this trait's ~260 existing users, with
 * no route file changes needed. A call site that explicitly extracted
 * `$model->id` for a route/redirect still needed a direct fix — see
 * `.ai/rules` for `Modules/Core/routes/**`/`Modules/Core/Livewire/**`.
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

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
