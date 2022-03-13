<?php
return function () {
    // $config = $this->getConfig();
    $debug = $this->get('dev');

    $view = new Phalcon\Mvc\View();
    $view->setDI($this);

    $folder = $this->has('project') ? sprintf('%s/twig/', APP_PATH) : '/base/frontend/twig';
    $view->setViewsDir($folder);
    $view->setMainView(null);

    $view->registerEngines([
        '.twig' => function($view, $di) use ($debug) {
            $options = array(
                'debug'                 => $debug,
                'charset'               => 'UTF-8',
                'base_template_class'   => 'Twig_Template',
                'strict_variables'      => $debug && isset($_GET['strict']), //false, //!$config->debug,
                'autoescape'            => false,
                'cache'                 => false,//$config->debug ? false : (APP_PATH . '/../.cache/'.VERSION.'/'),
                'auto_reload'           => !$debug,
                'optimizations'         => -1,
            );
            $functions = [
                new Twig_SimpleFunction('debug',function (...$args) { }),
                new Twig_SimpleFunction('microtime',function () { return microtime(false); }),
                new Twig_SimpleFunction('d','dump'),
                new Twig_SimpleFunction('json_encode', 'json_encode'),
                new Twig_SimpleFunction('i18n', function(string $str, array $placeholders = array()) {
                    return di()->getTwig()->renderTemplate($str, $placeholders);
                    // foreach ($placeholders as $k => $v) {
                    //     $str = preg_replace('/\{\{\s*'.$k.'\s*?\}\}/', $v, $str);
                    // }
                    // return $str;
                })
            ];
            $twig = new \Phalcon\Mvc\View\Engine\Twig($view, $di, $options, $functions);
            $twig->getTwig()->addExtension(new Twig_Extension_Debug());
            // $twig->getTwig()->addExtension(new Twig_Extensions_Extension_Text());
            $twig->getTwig()->addFilter(new Twig_SimpleFilter('t', function (string $path) {
                return $this->getI18n()->translatePath($path);
            }));

            $twig->getTwig()->addFilter(new Twig_SimpleFilter('ucfirst', function (string $text) {
                return ucfirst($text);
            }));

            $twig->getTwig()->addFilter(new Twig_SimpleFilter('markdown', function (string $value) : string {
                $Parsedown = new \Parsedown();
                return $Parsedown->line($value);
            }));

            $twig->getTwig()->addFilter(new Twig_SimpleFilter('transhelp', function (string $value) : string {
                preg_match('/\{<a class="translation\-helper.*?title="(.*?)">(.*?)<\/a>\}/', $value, $m);
                return sprintf('[%s=%s]', $m[1], $m[2]);
            }));

            $twig->getTwig()->addFilter(new Twig_SimpleFilter('spin', function (string $values, string $separator = '|') : string {
                $r = explode($separator, $values);
                $rnd = rand() % count($r);
                di()->getLog()->debug("Picked ${rnd} over ${values}");
                return $r[ $rnd ];
            }));

            $twig->getTwig()->addFilter(new Twig_SimpleFilter('jshrink', function( $val ) use ($debug) {
                return ($debug) ? $val : JShrink\Minifier::minify($val);
            }));

            $twig->getTwig()->addFilter(new Twig_SimpleFilter('i18n', function(/*string*/ $str, array $placeholders = array()) use ($debug) {
                # Buggy case to discover
                if (is_null($str)) {
                    if ($debug && isset($_GET['strict'])) {
                        // return "UNTRANSLATED";
                        throw new Exception("Input translation key is undefined. Look for entry in i18n table for language: " . $this->getLocale());
                    } else {
                        return "";
                    }
                }
                try {
                    return di()->getTwig()->renderTemplate($str, $placeholders);
                } catch (\Exception $e) {
                    throw new Exception("twigFilter::i18n: " . json_encode([ $str, $placeholders ]) . ' - '. $e->getMessage());
                }

                // foreach ($placeholders as $k => $v) {
                //     $str = preg_replace('/\{\{\s*'.$k.'\s*?\}\}/', $v, $str);
                // }
                // return $str;
            }));

            return $twig;
        },
        '.html' => 'Phalcon\\Mvc\\View\\Engine\\Php'
    ]);

    return $view;
};