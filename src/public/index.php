<?php
define('PHACTORY_PATH',     '/phactory');
define('APP_PATH',          '/app');
define('PRECONFIG_PATH',    '/preconfig');
define('START_MICROTIME',   microtime(true));
define('VERSION',           trim(file_get_contents('/VERSION')));
define('ENV',               getenv('ENV'));

try {

    $di = include(PHACTORY_PATH.'/bootstrap.php');
    $response = $di->getApp()->handle();
    $response->setHeader('X-Timing', round(microtime(true) - START_MICROTIME, 3) );
    $response->send();

} catch (Throwable $exception) {
    throw $exception;

    // if (!is_callable('di')) {
    //     throw $exception;
    // }
}

