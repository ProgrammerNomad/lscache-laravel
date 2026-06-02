<?php

namespace Litespeed\LSCache;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LSCacheMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @param  string|null              $lscache_control
     * @return mixed
     */
    public function handle($request, Closure $next, ?string $lscache_control = null)
    {
        $response = $next($request);

        if (!in_array($request->getMethod(), ['GET', 'HEAD']) || !$response->getContent()) {
            // Still flush any purge headers that were queued during this
            // request (e.g. a POST that triggers a cache purge).
            $this->flushPurgeHeaders($response);
            return $response;
        }

        $esi_enabled    = config('lscache.esi');
        $maxage         = config('lscache.default_ttl', 0);
        $cacheability   = config('lscache.default_cacheability');
        $guest_only     = config('lscache.guest_only', false);

        if ($maxage === 0 && $lscache_control === null) {
            $this->flushPurgeHeaders($response);
            return $response;
        }

        if ($guest_only && Auth::check()) {
            $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
            $this->flushPurgeHeaders($response);
            return $response;
        }

        $lscache_string = "max-age=$maxage,$cacheability";

        if (isset($lscache_control)) {
            $lscache_string = str_replace(';', ',', $lscache_control);
        }

        if (Str::contains($lscache_string, 'esi=on') == false) {
            $lscache_string = $lscache_string.($esi_enabled ? ',esi=on' : null);
        }

        if ($response->headers->has('X-LiteSpeed-Cache-Control') == false) {
            $response->headers->set('X-LiteSpeed-Cache-Control', $lscache_string);
        }

        $this->flushPurgeHeaders($response);

        return $response;
    }

    /**
     * Apply any X-LiteSpeed-Purge headers that were queued by LiteSpeedCache
     * during the current request lifecycle, then clear the queue.
     *
     * Using the response object (instead of PHP's header() function) ensures
     * compatibility with Laravel Octane (Swoole / RoadRunner) where the PHP
     * process is long-lived and native header() calls have no effect.
     * It also works correctly with LiteSpeed Web Server and OpenLiteSpeed,
     * both of which read purge instructions from the HTTP response headers.
     *
     * @param  \Symfony\Component\HttpFoundation\Response $response
     */
    protected function flushPurgeHeaders($response): void
    {
        foreach (LiteSpeedCache::getPendingPurges() as $purgeValue) {
            // Pass false as the third argument so that multiple purge calls
            // within the same request each produce their own header line
            // rather than overwriting one another.
            $response->headers->set('X-LiteSpeed-Purge', $purgeValue, false);
        }

        LiteSpeedCache::clearPendingPurges();
    }
}
