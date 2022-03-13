<?php

/**
 * tracker uri
 */
return function () : Redis {

    return new class extends Redis {
        private $trackerKey;

        public function __construct() {
            $config = parse_url($_ENV['WEB_REDIS_TRACKER_ADDRESS']);
            $config['path'] = ltrim($config['path'], '/');
            parse_str($config['query'], $qs);
            array_walk($qs, function(string &$v, string $k) {
                switch($k) {
                    case 'timeout'   : return $v = intval($v);
                    case 'persistent': return $v = boolval($v);
                    default:           return $v = null;
                }
            });

            debug("Tracker address", [ $config, $qs ]);

            // $redis = new Redis;
            $connect = $qs['persistent'] ? 'pconnect' : 'connect';
            $this->$connect(
                $config['host'],
                $config['port'],
                $qs['timeout'] ?: null
            );

            if (isset($config['path']) && !!$config['path'] && $config['path'] !== '/') {
                $this->select(ltrim($config['path'], '/'));
            }
            $this->trackerKey = $config['scheme'];
        }

        public function track(array $data = array()) {
            $data = array_merge($data,[
                'VERSION' => VERSION,
                'env' => ENV,
                'project' => [
                    'code' => di()->has('project') ? di()->getProject() : null,
                    'version' => di()->has('project') && !di()->get('dev') ? di()->getProjectVersion() : ENV,
                    'domain' => di()->getDomain()
                ]
            ]);
            $this->lpush($this->trackerKey, json_encode($data));
        }
    };
};