<?php

/**
 * Local to webserver only cache
 */
return function () {
    // if (di()->get('dev') && isset($_GET['uncached'])) {
    //     conf()->cache->enabled = false;
    // }

    return Phailgorithm\PhactoryLib\Model\Localcache::getInstance($_ENV['WEB_REDIS_LOCAL_CACHE_ADDRESS']);
};