<?php namespace Core;

use Throwable;

class Cache {

    protected static $instance;
    protected static $cache = array();
    protected static $miss  = array();
    protected static $hit   = array();

    public static function getInstance(string $address = null) {
        if (!static::$instance) {
            static::$instance = new class(new \Phalcon\Cache\Frontend\Data()) extends \Phalcon\Cache\Backend\Memory {
                public function ttl(string $keyName) {
                    return 0;
                }
            };
            debug(sprintf("[%s] cache.enabled = %d", get_called_class(), !!conf()->path('cache.enabled')));
            if (conf()->path('cache.enabled')) {
                try {
                    $config = parse_url($address);
                    debug("Cache address", $config);

                    parse_str($config['query'], $qs);
                    array_walk($qs, function(string &$v, string $k) {
                        switch($k) {
                            case 'lifetime' :  return $v = intval($v);
                            case 'persistent': return $v = boolval($v);
                            case 'timeout':    return $v = intval($v);
                            default:           return $v = null;
                        }
                    });

                    $default = ini_get('default_socket_timeout');

                    ini_set('default_socket_timeout', $qs['timeout'] ?? 3);
        // d(ini_get('default_socket_timeout'));

                    $redis = new \Phalcon\Cache\Backend\Redis\R2(
                        new \Phalcon\Cache\Frontend\Data([
                            "lifetime" => $qs['lifetime'] ?: null,
                        ]), [
                            "host"       => $config['host'],
                            "port"       => $config['port'],
                            "persistent" => $qs['persistent'] ?? true,
                            "index"      => intval(ltrim($config['path'], '/')),
                            'prefix'     => sprintf('%s:',$config['scheme'])
                        ]
                    );

                    $redis->_connect();
                    static::$instance = $redis;

                    ini_set('default_socket_timeout', $default);

                } catch (Throwable $e) {
                    if (di()->get('dev')) {
                        throw $e;
                    }
                }
            }
        }
        return static::$instance;
    }

    public function ttl(string $key) {
        return static::$instance->ttl($key);
    }


    public static function di() {
        return di()->getCache();
    }

    /**
     * Wrapper method to use callbacks, serialization and internal metrics
     */
    public function get(string $key, int $ttl, callable $fn ) {
        $i = static::di();

        try {
            if (isset(static::$cache[$key]) && !!static::$cache[$key]) {
                return static::$cache[$key];
            } else {
                $obj = $i->get($key);
            }
        } catch (\Exception $e) {
            if (di()->get('dev')) {
                throw $e;
            }
            $obj = null;
        }

        if (!$obj) {
            try {
                $t = microtime(true);
                $obj = $fn();
            } catch (\Exception $e) {
                throw $e;
            }
            if ($obj) {
                static::$cache[$key] = $obj;
                if ($ttl > 0) {
                    try {
                        $i->save($key, gzdeflate(serialize($obj), conf()->cache->compression), $ttl);
                    } catch (\Exception $e) {
                        if (di()->get('dev')) {
                            throw $e;
                        }
                        $obj = null;
                    }
                }
            }
            $key = $key. '@' . round(microtime(true) - $t, 2);
            static::$miss[ $key ] = true;

        } else {
            if (!is_string($obj)) {
                throw new \Exception("Error Processing Request", 1);
            }
            $obj = unserialize(gzinflate($obj));
            static::$cache[$key] = $obj;
            static::$hit[ $key ] = true;
        }
        return $obj;
    }


    public static function getMetrics() {
        return [
            'hit' => static::$hit,
            'miss' => static::$miss,
        ];
    }
}