<?php

return function() : string {

    if (isset($_ENV['ENABLE_COOKIE_DOMAIN']) && isset($_COOKIE['domain'])) {
        $_SERVER['HTTP_HOST'] = $_COOKIE['domain'];
    }
    return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname();


    // list ($_SERVER['HTTP_HOST'], $port) = explode(':', $_SERVER['HTTP_HOST']. ':80', 2) ;

    // $host = explode('.', $_SERVER['HTTP_HOST']);

    // $tld = array_pop($host);

    // return $tld;
};