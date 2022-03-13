<?php

use Monolog\Logger;
use Phalcon\Db\Adapter;
use Phalcon\Db\Profiler;
use Phalcon\Events\Event;
use Phalcon\Events\Manager;

return function () {
    $config = parse_url($_ENV['WEB_DB_WRITE_ADDRESS']);


    $db = new Phalcon\Db\Adapter\Pdo\Postgresql([
        'host'     => $config['host'],
        'port'     => $config['port'],
        'username' => $config['user'],
        'password' => $config['pass'],
        // 'dbname'   => $config->database->dbname,
        // 'charset'  => $config->database->charset
        'options' => [
            PDO::ERRMODE_EXCEPTION => true
        ]
    ]);

    return $db;
};