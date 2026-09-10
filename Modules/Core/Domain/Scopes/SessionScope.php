<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Core\Domain\Support\SessionContext;

/**
 * Filters every academic/financial table to the active
 * `(academic_year, term)` session (Book A Part 1.3). A record with a null
 * `term_id` is year-scoped rather than term-scoped (§0.4) and stays
 * visible across every term of its year.
 *
 * @implements Scope<Model>
 */
final class SessionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! SessionContext::isSet()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('academic_year_id'), SessionContext::yearId());

        $termId = SessionContext::termId();

        if ($termId !== null) {
            $termColumn = $model->qualifyColumn('term_id');

            $builder->where(function (Builder $query) use ($termColumn, $termId): void {
                $query->where($termColumn, $termId)->orWhereNull($termColumn);
            });
        }
    }
}
