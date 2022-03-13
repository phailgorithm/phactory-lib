<?php namespace Core;

use Illuminate\Support\Arr;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Controller;
use Illuminate\Support\Collection;

class PublicRootFilesController extends OutputController {

    /**
     */
    public function txtfileAction(string $file) {
        $txtfile = sprintf('%s/%s/public/' . $file  . '.txt', PHACTORY_PATH, $this->di->getProject());
        if (file_exists($txtfile)) {
            $this->response->setContentType('text/plain; charset=UTF-8');
            $this->view->setContent(file_get_contents($txtfile));
        } else {
            $this->dispatcher->forward([
                'namespace' => 'Core',
                'controller' => 'Error',
                'action' => 'http404'
            ]);
        }
    }


    /**
     */
    public function faviconAction() {
        $icofile = sprintf('%s/%s/public/favicon.ico', PHACTORY_PATH, $this->di->getProject());
        if (file_exists($icofile)) {
            $this->response->setContentType('image/x-icon');
            $this->view->setContent(file_get_contents($icofile));
        } else {
            $this->dispatcher->forward([
                'namespace' => 'Core',
                'controller' => 'Error',
                'action' => 'http404'
            ]);
        }
    }


}
