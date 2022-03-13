<?php

use Phalcon\Config\Adapter\Yaml;
//use Phalcon\Config\Adapter\Json;

return function ($yml = null, array $data = array()) : Phalcon\Config {

    $locale = $this->getLocale();
    if (strpos($locale, '_') !== false ) {
        $langs = [ explode('_', $locale)[0] , $locale ];
    } else {
        $langs = [ $locale ];
    }

    # PHP7 Hack, anonymous classes to implement `path()` method
    # that is not present in Phalcon (despite being documented on https://docs.phalcon.io/3.4/en/api/phalcon_config )
    $return = new class extends Phalcon\Config {
        /**
         * Using dot notation to retrive transalations
         *
         * @param string $path key in dot notation
         * @param array $placeholders  key-value  pairs for replacing {{placeholders}}
         * @param string [$defaultValue] default value if key not found. if null, key is returned
         *
         * @return string
         */
        public function translatePath($path, array $placeholders = array(), $defaultValue = null, $delimiter = '.') : string {
            $data = $this;

            foreach (explode($delimiter, $path) as $level => $token) {
                # Skip first `i18n.` prefix
                if ($level == 0 && $token == 'i18n') { continue; }

                $data = $data->get($token);

                if (in_array($data, ["0", "1", "true", "false"])) {
                    return filter_var($data, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";
                }

                if (!$data) {
                    return $defaultValue ?: $path;
                }
            }

            $data = $this->replace($data, $placeholders);

            return di()->get('dev') && isset($_GET['translations']) ? $this->highlight($path, $data) : $data;
        }

        // @TODO - improve this
        private function replace(string $string, array $placeholders = array()) : string {
            return di()->getTwig()->renderTemplate($string, $placeholders);
            // foreach ($placeholders as $k => $v) {
            //     $string = preg_replace('/\{\{\s*'.$k.'\s*?\}\}/', $v, $string);
            // }
            // return $string;
        }

        /**
         * Fetches one variation of a multi-value translation
         *
         * @param int $seed random seed
         * @param string $path key in dot notation
         * @param array $placeholders  key-value  pairs for replacing {{placeholders}}
         * @param string [$defaultValue] default value if key not found. if null, key is returned
         *
         * @return string
         */
        public function rand(int $seed = null, string $path, array $placeholders = array(), $defaultValue = null, $delimiter = '.') : string {
            if (!!$seed) {
                srand($seed);
            }
            $variations = $this->path($path);
            if (is_null($variations)) {
                $variations = $defaultValue;
            }
            if (!is_object($variations)) {
                $variations = array($variations);
            } else {
                $variations = $variations->toArray();
            }
            $v = $variations[ rand(0, count($variations)-1) ];

            if (!!$seed) {
                srand(microtime(true));
            }

            return $this->replace($v, $placeholders);

        }

        protected function highlight($k, $v) : string {
            if ($_GET['translations'] == 'markup') {
                return sprintf('[%s] => %s', $k, $v);
            }
            return sprintf('{<a class="translation-helper text-red-500" title="%s">%s</a>}',  $k, $v);
        }

        public function toArray() : array {
            $data = parent::toArray();
            if (isset($_ENV['WEB_ALLOW_TRANSLATIONS_HIGHLIGHT']) && boolval($_ENV['WEB_ALLOW_TRANSLATIONS_HIGHLIGHT']) === true && isset($_GET['translations'])) {
                $x = array_dot($data);
                foreach ($x as $k =>$v) {
                    $x[$k] = $this->highlight($k, $v);
                }
                $data = array_undot($x);
            }
            return $data;
        }
    };


    /**
     * Loads i18n from db directly, without cache if `WEB_USE_LIVE_TRANSLATIONS`
     */
    if (isset($_ENV['WEB_USE_LIVE_TRANSLATIONS']) && boolval($_ENV['WEB_USE_LIVE_TRANSLATIONS']) === true) {
        $locale = $locale = $this->getLocale();
        $items = [];
        $q = "SELECT key, ${locale} AS value FROM i18n WHERE project IS NULL AND ${locale} != '' AND ${locale} IS NOT NULL ORDER BY key ASC";
        $q = $this->getDb()->query($q);
        while ($item = $q->fetch(\PDO::FETCH_ASSOC)) {
            $items[$item['key']] = $item['value'];
        }

        $q = "SELECT key, ${locale} AS value FROM i18n WHERE project = (SELECT id FROM project WHERE code = ?) ORDER BY key ASC";

        $q = $this->getDb()->query($q, [ $this->getProject() ]);
        while ($item = $q->fetch(\PDO::FETCH_ASSOC)) {
            $items[$item['key']] = $item['value'];
        }
        $items = array_undot($items);
        $return->merge( new Phalcon\Config( array_undot($items)));

    } else {
        $callbacks = [];
        foreach ($langs as $l) {
            foreach ([
                sprintf('/base/locale/%s.yml', $l ),
                $this->has('project') ? sprintf('%s/%s/locale/%s.yml', PHACTORY_PATH,  $this->getProject() , $l ) : null
            ] as $p) {
                if (!!$p && file_exists($p)) {
                    $return->merge( new Yaml( $p ,[

                    ]));
                }
            }
        }
    }

    return $return;

    /*
    if (!$yml) {
        $yml = [];
    } elseif (is_string($yml)) {
        $yml = [ $yml ];
    }

    $config = new Phalcon\Config();

    foreach ($yml as $file) {
        $config->merge(new Yaml($file, [
            "!JSON" => function($value) use ($data) {
                $items = $data;

                $keypath = explode('.', $value);

                foreach ($keypath as $k => $segment) {
                    if (!is_array($items) || !array_key_exists($segment, $items)) {
                        // hard debug missing translations
                        if (!empty($_GET['missingTranslations']) && app()->getEnv() == 'dev') {
                            throw new Exception("translation key not found: $value");
                        }

                        // log missing translations here
                        $items[$segment] = (count($keypath) - 1 == $k) ? "{[( ".$value." )]}" : [];
                    }

                    $items = $items[$segment];
                }

                return $items;
            },
        ]));
    }

    return $config;
    */
};
