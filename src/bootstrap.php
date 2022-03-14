<?php

use Phalcon\Mvc\Application;

include(APP_PATH.'/phalcon/vendor/autoload.php');

$timings = array();

$timings['sys-init'][] = microtime(true);
$di = new Phalcon\Di\FactoryDefault;
foreach (['app'] as $filename) {
    $di->setShared($filename, require_once(PHACTORY_PATH. '/di/'.$filename.'.php'));
}
$timings['sys-init'][] = microtime(true);

//di()->getTiming()->addMeasure ( 'sys-init', START_MICROTIME, microtime(true) );


$preEnv = array (
    'timing',
    'log',
    'view',
    'twig',
    'locale',
    'db',
    'httphost',
    'project',
    'domain',
);

$postEnv = array (
    'dev',
    'error',
    'i18n',
    'config',
    'cache',
    'localcache',
    'tracker',
    'crypt',
    'router',
    'url',
    'queue',
    'session',
    'phalconbar'
);

foreach ($preEnv as $filename) {
    $timings['pre-di-'. $filename ][] = microtime(true);
    $di->setShared($filename, require_once(PHACTORY_PATH. '/di/'.$filename.'.php'));
    $timings['pre-di-'. $filename ][] = microtime(true);
}

$timings['dotenv'][] = microtime(true);

# Mutable allows local env to override existing ones
$localEnvs = [
    'PROJECT' => '.env',
    'DOMAIN'  => sprintf('.env.%s', $di->getDomain())
];
$dir = sprintf('%s/', APP_PATH);
foreach ($localEnvs as $k => $file) {
    if (!file_exists($dir.$file)) {
        trigger_error("Missing local .env: ${dir}/${file}", E_USER_WARNING);
    }
    $dotenv = Dotenv\Dotenv::createMutable($dir, $file);
    $GLOBALS[ $k . '_ENVS'] = $dotenv->safeLoad();
}

# For env-specific - can be omitted
$file = sprintf('.env.%s', ENV);
if (file_exists($dir.$file)) {
    $dotenv = Dotenv\Dotenv::createMutable($dir, $file);
    $GLOBALS['PROJECT_ENVS'] = array_merge($GLOBALS['PROJECT_ENVS'], $dotenv->safeLoad());
}

$timings['dotenv'][] = microtime(true);



$timings['post-sentry'][] = microtime(true);

if (isset($_ENV['SENTRY_DSN_BACKEND']) || isset($_ENV['PROJECT_SENTRY_DSN_BACKEND'])) {
  Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($di) : void {
     $scope->setTag('project', $di->getProject());
  });

  Sentry\init([
    'dsn' => $_ENV['PROJECT_SENTRY_DSN_BACKEND'] ?? $_ENV['SENTRY_DSN_BACKEND'],
    'traces_sample_rate' => 1.0,
    'max_breadcrumbs' => 50,
    'release' => VERSION,
    'environment' => ENV,
  ]);
}

//di()->getTiming()->addMeasurePoint('post-sentry');
$timings['post-sentry'][] = microtime(true);


// # Project-specific autoload

// ## Autoload
// $localautoload = sprintf('%s/%s/phalcon/vendor/autoload.php', APP_PATH, $di->getProject());
// if (file_exists($localautoload)) {
//     include($localautoload);
// }


foreach ($postEnv as $filename) {
    $timings['post-di-'. $filename][] = microtime(true);
    $di->setShared($filename, require_once(PHACTORY_PATH. '/di/'.$filename.'.php'));
    $timings['post-di-'. $filename][] = microtime(true);
}

Monolog\ErrorHandler::register(di()->getLog());



## Bootstraps
$localBootstrap = sprintf('%s/phalcon/bootstrap.php', APP_PATH);
if (file_exists($localBootstrap)) {
    include($localBootstrap);
}

## Dependecy injections
$localDis = sprintf('%s/phalcon/local-di.php', APP_PATH);
$localDis = file_exists($localDis) ? include($localDis) : [];

foreach ($localDis as $filename) {
    $timings['local-di-'. $filename ][] = microtime(true);
    di()->setShared($filename, require_once(APP_PATH. '/phalcon/di/'.$filename.'.php'));
    $timings['local-di-'. $filename ][] = microtime(true);
}

foreach ($timings as $k => $v) {
    di()->getTiming()->add( $k, $v[0], $v[1]);
}

return $di;
