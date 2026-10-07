<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Volume 1 §9.1 — `Accept-Language` selects English, chiShona (`sn`) or isiNdebele (`nd`) for API
 * responses. The header is negotiated by quality value against those three; a region tag
 * (`sn-ZW`, `en_ZW`) matches its language, and anything unsupported falls back to English rather
 * than failing the request. The chosen language is echoed in `Content-Language`.
 */
final class SetApiLocale
{
    public const array SUPPORTED = ['en', 'sn', 'nd'];

    public const string FALLBACK = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::negotiate((string) $request->header('Accept-Language'));
        app()->setLocale($locale);

        // `serp.api-locale` runs first in the `serp.api` group (before `auth:sanctum` etc.), so a
        // later middleware's exception (401/403/422/...) propagates up through this `$next()` call
        // rather than returning a response — the header below would never be set. Render it here,
        // the same way Illuminate\Foundation\Http\Kernel::handle() does in its own catch block, so
        // every API response carries Content-Language, including an error one.
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $handler = app(ExceptionHandler::class);
            $handler->report($e);
            $response = $handler->render($request, $e);
        }

        $response->headers->set('Content-Language', $locale);
        $response->headers->set('Vary', trim($response->headers->get('Vary', '').', Accept-Language', ', '));

        return $response;
    }

    public static function negotiate(string $header): string
    {
        $candidates = [];

        foreach (explode(',', $header) as $position => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));
            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $m) === 1) {
                    $quality = (float) $m[1];
                }
            }

            $language = preg_split('/[-_]/', $tag)[0] ?? '';

            if ($quality > 0 && in_array($language, self::SUPPORTED, true)) {
                $candidates[] = ['language' => $language, 'quality' => $quality, 'position' => $position];
            }
        }

        usort($candidates, fn (array $a, array $b): int => [$b['quality'], $a['position']] <=> [$a['quality'], $b['position']]);

        return $candidates[0]['language'] ?? self::FALLBACK;
    }
}
