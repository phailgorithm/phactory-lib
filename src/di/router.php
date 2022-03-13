<?php
use Phalcon\Mvc\Router;
use Phalcon\Config;
use Phalcon\Config\Adapter\Yaml;

return function() : Router {
    $router = new Router(false); // Here tell the router to not using default routes
    $router->setDI($this);
    $router->setUriSource(Router::URI_SOURCE_SERVER_REQUEST_URI);

    # @TODO - db
    $routes = [
        #  BASE
        '/base/config/routes.yml',

        # PROJECT
        sprintf('%s/%s/config/routes.yml', PHACTORY_PATH, $this->getProject()),

        # PROJECT - DOMAIN
        sprintf('%s/%s/config/%s/routes.yml', PHACTORY_PATH, $this->getProject(), $this->getDomain()),

        # PROJECT - DOMAIN - ENV
        sprintf('%s/%s/config/%s/routes.%s.yml', PHACTORY_PATH, $this->getProject(), $this->getDomain(), ENV),

    ];

    $routing = new Config;

    foreach ($routes as $conf) {
        if (file_exists($conf)) {
             $routing->merge( new Yaml($conf, [
                            '!ENV' => function($value) {
                                return getenv($value);
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
                            }
                        ])
            );
        }
    }

    conf()->cache->routing = new Config();

    foreach ($routing as $pattern => $route) {
        if ($route->get('domain', null) && $route->get('domain') !== $this->getDomain()) {
            continue;
        }

        $name = str_replace('..','.', trim(strtolower(strtr($route->controller,'\\:','..')), '. '));

        $router
            ->add(
                $pattern,
                $route->controller,
                explode(',', $route->get('methods', 'GET,HEAD')),
                $route->get('position', Router::POSITION_FIRST) )
            ->setName($route->get('name', $name ));

        conf()->cache->routing[ $name ] = new Config([
            'ttl'   => $route->get('ttl', null),
            'grace' => $route->get('grace', null),
        ]);
    }

    $notFound = [
        'namespace' => 'Phailgorithm\\PhactoryLib',
        'controller' => 'Error',
        'action' => 'check'
    ];

    if (class_exists(sprintf('%s\\%s\\%sController',
            $this->getProject(),
            $notFound['namespace'],
            $notFound['controller']))) {
        $notFound['namespace'] = sprintf('%s\\%s', $this->getProject(), $notFound['namespace']);
    }

    $router->notFound($notFound);

    return $router;
};