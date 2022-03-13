<?php namespace Phailgorithm\PhactoryLib\Controller;

class BasicAuthController extends OutputController {

    protected static function getAuthUser() : string { return di()->getProject(); }
    protected static function getProtectedEnvs() : array { return ['staging']; }

    public function beforeExecuteRoute($dispatcher) {
        parent::beforeExecuteRoute($dispatcher);
        static::checkAuth($dispatcher);
    }


    public static function checkAuth($dispatcher) {
        if ($_SERVER['PHP_AUTH_USER'] !== static::getAuthUser() && in_array($_ENV['ENV'], static::getProtectedEnvs())) {
            $dispatcher->forward([
                'namespace' => 'Core',
                'controller'=> 'Error',
                'action' => 'http401'
            ]);
            return [];
        }
    }
}

