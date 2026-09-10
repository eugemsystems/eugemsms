<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\ModuleNotEnabledException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\SchoolModule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 6 / Volume 1 §5.3. Attach with the module code
 * the route belongs to: `->middleware('serp.module-enabled:FIN')`. `CORE`
 * itself is mandatory for every school (Volume 1 §6.0) and needs no
 * `school_modules` row to be considered enabled.
 */
final class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $school = SchoolContext::current();

        if ($school === null) {
            return $next($request);
        }

        $entitlement = SchoolModule::where('module_code', $moduleCode)->first();

        $isEnabled = $entitlement !== null
            ? $entitlement->isCurrentlyEnabled()
            : $moduleCode === 'CORE';

        if (! $isEnabled) {
            throw new ModuleNotEnabledException(
                "The [{$moduleCode}] module is not enabled for this school.",
                ['module_code' => $moduleCode, 'school_id' => $school->id],
            );
        }

        return $next($request);
    }
}
