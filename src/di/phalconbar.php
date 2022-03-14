<?php
if (di()->get('dev') && !isset($_ENV['DEV_DEBUGBAR_DISABLED'])) {
    $debugbar = new Snowair\Debugbar\ServiceProvider('/debugbar.php');
    $debugbar->start();
    // d($debugbar);
    return $debugbar;
}