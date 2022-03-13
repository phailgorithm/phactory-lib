<?php

if (di()->get('dev')) {
    $whoops = new \Whoops\Run;
    // Redis deprecation of setTimeout()
    $whoops->silenceErrorsInPaths("/Unknown/", $levels = E_DEPRECATED);
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
    $whoops->register();
}
else {

    set_exception_handler(function(...$args) {

        if ($args[0] instanceOf Phailgorithm\PhactoryLib\Core\Exception\NotFound) {
            if (class_exists(sprintf('%s\\Phailgorithm\\PhactoryLib\\Core\\ErrorController', di()->getProject()))) {
                di()->getDispatcher()->setNameSpaceName( sprintf('%s\\Core', di()->getProject()) );
            } else {
                di()->getDispatcher()->setNameSpaceName( 'Phailgorithm\\PhactoryLib\\Core' );
            }
            di()->getDispatcher()->setControllerName( 'Error');
            di()->getDispatcher()->setActionName('http404' );
            di()->getDispatcher()->setParams([]);
            di()->getDispatcher()->dispatch();
            di()->getDispatcher()->getReturnedValue()->send();

            di()->getLog()->warning($args[0]->getMessage(), [
                'exception' => $args[0]
            ]);

        } else if ($args[0] instanceof Throwable) {
            if (class_exists(sprintf('%s\\Phailgorithm\\PhactoryLib\\Controller\\ErrorController', di()->getProject()))) {
                di()->getDispatcher()->setNameSpaceName( sprintf('%s\\Phailgorithm\\PhactoryLib\\Controller', di()->getProject()) );
            } else {
                di()->getDispatcher()->setNameSpaceName( 'Phailgorithm\\PhactoryLib\\Controller' );
            }

            di()->getDispatcher()->setControllerName( 'Error');
            di()->getDispatcher()->setActionName('http500' );
            di()->getDispatcher()->setParams([
                $args[0]
            ]);
            di()->getDispatcher()->dispatch();
            di()->getDispatcher()->getReturnedValue()->send();

            di()->getLog()->error($args[0]->getMessage(), [
                'exception' => $args[0]
            ]);

            try {

                /*
                if (!Sentry\SentrySdk::getCurrentHub()->getClient()) {
                    $defaultSentry = '';
                    if ($defaultSentry) {
                        Sentry\init([
                            'dsn' => $defaultSentry,
                            'traces_sample_rate' => 1.0,
                            'max_breadcrumbs' => 50,
                            'release' => VERSION,
                            'environment' => ENV,
                        ]);
                    }
                }
                */

                \Sentry\captureException( $args[0] );
            } catch (\Throwable $t) {

            }
        // } else {
            /*
            di()->getDispatcher()->setNameSpaceName( 'Core' );
            di()->getDispatcher()->setControllerName( 'Error');
            di()->getDispatcher()->setActionName('http500' );
            di()->getDispatcher()->setParams([]);
            di()->getDispatcher()->dispatch();
            di()->getDispatcher()->getReturnedValue()->send();
            */
            //di()->getLog()->alert('Unknown error', $args);

        }

    });

    set_error_handler(function(...$args) {
        if (in_array($args[0],
            array(
                E_USER_ERROR,
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_CORE_WARNING,
                E_COMPILE_ERROR,
                E_COMPILE_WARNING,
                E_RECOVERABLE_ERROR))) {
            throw new ErrorException(
                $message = sprintf('%s: %s', array_search($args[0], get_defined_constants()), $args[1]),
                $code = $args[0],
                $severity = E_ERROR,
                $filename = $args[2],
                $lineno = $args[3]
            );
        }
        // di()->getLog()->notice($args[1], [
        //     'file' => $args[2],
        //     'line' => $args[3]
        // ]);
    });

}
