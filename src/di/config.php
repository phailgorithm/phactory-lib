<?php
use Phalcon\Config;


/**
 * Precedence order: Base, Base/env, Project, Project/env, Domain, Domain/env
 */
return function () : Config {
    $config = new Config;
    $callbacks = [
        '!ENV' => function($value) {
            return isset($_ENV[$value]) ? $_ENV[$value] : null;
        },
        '!DURATION' => function($value) {
            if (is_numeric($value)) {
                return $value;
            }
            $duration = new Khill\Duration\Duration($value);
            return intval($duration->toSeconds());
        },
        '!ENV_DURATION' => function($value) {
            $value = getenv($value);
            if (is_numeric($value)) {
                return $value;
            }
            $duration = new Khill\Duration\Duration($value);
            return intval($duration->toSeconds());
        },
        '!GET_STATIC_HOST'=> function($value) {
            // if ($this->get('dev')) {
            //     return '/static/' . $this->getProject();
            // } else {
                return $_ENV['PROJECT_STATIC_HOST'] ?? $_ENV['PROJECT_MAIN_STATIC_HOST'] ?? '/static';
            // }
        },
        '!IS_ENV' => function ($env) {
            return in_array(ENV, explode(',', $env));
        },
        '!DATE' => function ($format) {
            return date($format);
        },
        '!REPLACE_SERVER' => function ($str) {
            return $this->getTwig()->renderTemplate($str, $_SERVER);
        }
    ];

    $baseconfs = [
        '/base/config/app',
        '/base/config/'. ENV ];

    foreach ($baseconfs as $conf) {
        $conf = sprintf('%s.yml', $conf);
        if (file_exists($conf)) {
            $config->merge(new Phalcon\Config\Adapter\Yaml($conf, $callbacks));
        }
    }


    $confs = [
        sprintf('%s/%s/config/app.yml', PHACTORY_PATH, $this->getProject()),
        sprintf('%s/%s/config/%s.yml', PHACTORY_PATH, $this->getProject(), ENV),
        sprintf('%s/%s/config/%s/app.yml', PHACTORY_PATH, $this->getProject(), $this->getDomain()),
        sprintf('%s/%s/config/%s/%s.yml', PHACTORY_PATH, $this->getProject(), $this->getDomain(), ENV),
    ];

    foreach ($confs as $conf) {
        if (file_exists($conf)) {
            $config->merge(new Phalcon\Config\Adapter\Yaml($conf, $callbacks));
        }
    }

    return $config;
};


