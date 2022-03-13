<?php

/**
 * Sharded, distributed cache
 */
return function () {
    if (di()->get('dev') && isset($_GET['uncached'])) {
        conf()->cache->enabled = false;
    }

    return Core\Cache::getInstance($_ENV['WEB_REDIS_SHARED_CACHE_ADDRESS']);
};