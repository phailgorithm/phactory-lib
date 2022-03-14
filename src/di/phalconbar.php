<?php
if (di()->get('dev') && !isset($_ENV['DEV_DEBUGBAR_DISABLED'])) {
    $debugbar = new Snowair\Debugbar\ServiceProvider(sprintf('%s/config/debugbar.php', APP_PATH));
    $debugbar->start();
    // d($debugbar);
    return $debugbar;
}