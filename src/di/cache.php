<?php

/**
 * Sharded, distributed cache
 */
return function () {
    if (di()->get('dev') && isset($_GET['uncached'])) {
        conf()->cache->enabled = false;
    }

    return Phailgorithm\PhactoryLib\Model\Cache::getInstance($_ENV['WEB_REDIS_SHARED_CACHE_ADDRESS']);
};