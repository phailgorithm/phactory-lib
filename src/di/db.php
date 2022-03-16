<?php

use Monolog\Logger;
use Phalcon\Db\Adapter;
use Phalcon\Db\Profiler;
use Phalcon\Events\Event;
use Phalcon\Events\Manager;

return function () {
    $config = parse_url($_ENV['WEB_DB_ADDRESS']);


    $db = new Phalcon\Db\Adapter\Pdo\Postgresql([
        'host'     => $config['host'],
        'port'     => $config['port'],
        'username' => $config['user'],
        'password' => $config['pass'],
        'dbname'   => trim($config['path'],'/'),
        // 'charset'  => $config->database->charset
        'options' => [
            PDO::ERRMODE_EXCEPTION => true
        ]
    ]);

    if (isset($_ENV['DB_PROFILER'])) {
        $levels = explode(',', $_ENV['DB_PROFILER']);
        foreach ($levels as $i => $e) {
            $e = explode(':', $e);
            $e[0] = floatval($e[0]);
            $levels[$i] = $e;
        }
        // usort($levels, function($a, $b) {
        //     return $a[0] < $b[0];
        // });

        $profiler = new Profiler;
        $logger   = $this->getLog();

        $eventsManager = new Manager;
        $eventsManager->attach( "db", function(Event $event, Adapter $db, $params)  use ($profiler, $logger, $levels) {

            if ($event->getType() == 'beforeQuery') {
                $profiler->startProfile($db->getSQLStatement(), $db->getSQLVariables(), $db->getSQLBindTypes());
            }
            if ($event->getType() == 'afterQuery') {
                $profiler->stopProfile();

                $elapsed = $profiler->getLastProfile()->getTotalElapsedSeconds();

                $min = $levels[count($levels)-1][0];
                if ($elapsed > $min) {
                    $level = $levels[count($levels)-1];

                    foreach ($levels as $ll) {
                        if ($elapsed > $ll[0]) {
                            $level = $ll;
                            break;
                        }
                    }
                    $logger->addRecord( Logger::toMonologLevel( $level[1] ) , 'SQL slow query', [
                        'query'   => $db->getSQLStatement(),
                        'vars'    => $db->getSQLVariables(),
                        'elapsed' => $elapsed
                    ]);

                }
            }
        });
        $db->setEventsManager($eventsManager);
    }


    return $db;
};