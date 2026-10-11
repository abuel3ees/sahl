<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen language (Arabic or English): a "?lang=" link wins, then the
 * "lang" cookie written by the language toggle, then the app default.
 */
class SetLocale
{
    public const COOKIE = 'lang';

    /** @var list<string> */
    public const SUPPORTED = ['ar', 'en'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query(self::COOKIE) ?? $request->cookie(self::COOKIE);

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
