<?php
if (di()->get('dev') && !isset($_ENV['DEV_DEBUGBAR_DISABLED'])) {
    $debugbar = new Snowair\Debugbar\ServiceProvider('/base/config/debugbar.php');
    $debugbar->start();
    // d($debugbar);
    return $debugbar;
}