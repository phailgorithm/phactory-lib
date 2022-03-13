<?php namespace Core;

use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;

class SystemController extends OutputController
{

    public function afterExecuteRoute(Dispatcher $dispatcher) {
        parent::afterExecuteRoute($dispatcher);

        $this->response->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

        $this->response->setJsonContent( $this->view->getParamsToView()['view'], JSON_PRETTY_PRINT ) ;


    }


    public function checkAction() : array {
        $this->view->disable();

        if (isset($_GET['error'])) {
            throw new \Exception("Simulated error");
        }

        if (file_exists( $_ENV['UPGRADE_FILE'] )) {
            $this->response->setStatusCode(410, 'System Maintenance');
            return [
                'status' => 'bad',
                'file' => $_ENV['UPGRADE_FILE']
                // 'db' => $this->di->getDb()->query( "SELECT inet_server_addr()" )->fetch() [0],
            ];
        } else {
            return [
                'status' => 'OK',
                'file' => $_ENV['UPGRADE_FILE']
                // 'db' => $this->di->getDb()->query( "SELECT inet_server_addr()" )->fetch() [0],
            ];
        }
    }
}