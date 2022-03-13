<?php

use \Phalcon\Security\Random;

return function () {

    $config = parse_url($_ENV['WEB_REDIS_SESSION_ADDRESS']);
    debug("Session address", $config);
    parse_str($config['query'], $qs);
    array_walk($qs, function(string &$v, string $k) {
        switch($k) {
            case 'lifetime' :  return $v = intval($v);
            case 'persistent': return $v = boolval($v);
            default:           return $v = null;
        }
    });
    $session = new \Phalcon\Session\Adapter\Redis(array(
        "host"       => $config['host'],
        "port"       => $config['port'],
        "persistent" => $qs['persistent'] ?? true,
        "index"      => ltrim($config['path'], '/'),
        'prefix'     => sprintf(':%s:',$config['scheme']),
        "lifetime"   => $qs['lifetime'] ?: null,
        'uniqueId'   => di()->getProject(),
    ));

    # @TODO
    $session->setName( di()->getProject() );

    $session->setId(
        $_COOKIE[ di()->getProject() ] ?? (new Random)->base64Safe(rand(16,24))
    );

    return $session;
};