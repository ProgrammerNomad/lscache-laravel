<?php

namespace Litespeed\LSCache;

class LiteSpeedCache
{
    /**
     * Purge headers queued during the current request lifecycle.
     * Consumed by LSCacheMiddleware to attach them to the response object,
     * which makes them work correctly under Laravel Octane (Swoole / RoadRunner)
     * as well as in standard PHP-FPM deployments.
     *
     * @var list<string>
     */
    protected static array $pendingPurges = [];

    public function purge(string $items, bool $stale = true): void
    {
        // Build the header value without touching any instance state so that
        // successive calls with different $stale values do not bleed into each
        // other (the previous implementation had a $stale_key instance property
        // that was set on stale=true but never reset on stale=false).
        $headerValue = ($stale ? 'stale,' : '') . $items;

        static::$pendingPurges[] = $headerValue;
    }

    public function purgeAll(bool $stale = true): void
    {
        $this->purge('*', $stale);
    }

    public function purgeTag(string $tag, bool $stale = true): void
    {
        $this->purge('tag=' . $tag, $stale);
    }

    public function purgeTags(array $tags, bool $stale = true): void
    {
        if (count($tags)) {
            $this->purge(implode(',', array_map(fn (string $tag) => 'tag=' . $tag, $tags)), $stale);
        }
    }

    public function purgeItems(array $items, bool $stale = true): void
    {
        if (count($items)) {
            $this->purge(implode(',', $items), $stale);
        }
    }

    /**
     * Return all purge header values queued since the last flush.
     *
     * @return list<string>
     */
    public static function getPendingPurges(): array
    {
        return static::$pendingPurges;
    }

    /**
     * Clear the pending purge queue (called by LSCacheMiddleware after the
     * headers have been written to the response object).
     */
    public static function clearPendingPurges(): void
    {
        static::$pendingPurges = [];
    }
}
