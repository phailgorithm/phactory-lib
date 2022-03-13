<?php

use Illuminate\Support\Collection;

return function() : Twig_Environment {

    return new class extends Twig_Environment {
        public function __construct () {
            parent::__construct(new Twig_Loader_Array([]), $options = array(), $functions = [ ]);

            $this->addFilter(new Twig_SimpleFilter('shuffle', function (Collection $values) : Collection {
                return $values->shuffle();
            }));

            $this->addFilter(new Twig_SimpleFilter('random', function (Collection $values, int $min, int $max) : Collection {
                $n = rand($min, $max);
                return $values->random(max(1, min($n, $values->count())));
            }));

            $this->addFilter(new Twig_SimpleFilter('sort', function (Collection $values) : Collection {
                return $values->sort();
            }));

            $this->addFilter(new Twig_SimpleFilter('spin', function (string $values, string $separator = ',') : string {
                $r = explode($separator, $values);
                return $r[ rand() % count($r) ];
            }));

        }

        public function renderTemplate(string $template, array $vars) {
            $id = md5($template);
            $this->setLoader( new Twig_Loader_Array([
                $id => $template
            ]));
            return $this->render($id, $vars);
        }
    };
};