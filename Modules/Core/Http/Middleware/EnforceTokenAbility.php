<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 7 (API only). Route declares its required
 * abilities: `->middleware('serp.token-ability:fees.read,fees.write')`.
 * A student token cannot act with a parent's rights even if the account
 * is linked (Volume 1 ADR-008). Since CORE-05, `App\Models\User` always
 * carries `tokenCan()` via `HasApiTokens` — the `method_exists` guard
 * stays anyway as a defensive check against any other authenticatable
 * a future guard might introduce (deliberately duck-typed rather than
 * an `instanceof` on a Sanctum contract, since `HasApiTokens` doesn't
 * declare one). PHPStan flags this as tautological against today's
 * single concrete guard/provider config — an accepted, permanent
 * notice, the same as `BelongsToSession`'s unused-trait one.
 */
final class EnforceTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null || $abilities === [] || ! method_exists($user, 'tokenCan')) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if (! $user->tokenCan($ability)) {
                throw new InsufficientScopeException(
                    "The current token does not carry the [{$ability}] ability.",
                    ['ability' => $ability],
                );
            }
        }

        return $next($request);
    }
}
