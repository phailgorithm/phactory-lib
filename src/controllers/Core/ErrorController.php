<?php namespace Core;

use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Controller;
use Illuminate\Support\Str;

class ErrorController extends OutputController {

    /**
     * Bypasses usage of getMatchedRoute() because there is none.s
     */
    public function beforeExecuteRoute(Dispatcher $dispatcher) {
        $this->route = 'base.'.strtolower($dispatcher->getControllerName() .'.'. $dispatcher->getActionName());
    }

    public function afterExecuteRoute(Dispatcher $dispatcher) {
        $this->view->enable();
        # When project-specific files are missing, fallback to base
        if (!$this->view->getMainView() &&
            !file_exists($this->view->getViewsDir() . '/error/'. $dispatcher->getActionName().'.twig'))
        {
            $this->view->setMainView('_base/error/'.$dispatcher->getActionName());
        }
        parent::afterExecuteRoute($dispatcher);
    }


    public function http401Action(string $message = 'Secured') : array {
        $this->response->setStatusCode(401);
        // $this->view->setMainView('error/http500');
        $this->response->setHeader('WWW-Authenticate', 'Basic realm=' . $message);
        $this->response->setContent('Unauth');
        return [
            'error' => [
                'code' => 401
            ]
        ];
    }

    public function http410Action() : array {
        $this->response->setStatusCode(410);
        return [
            'error' => [
                'code' => 410,
                'title' => 'Page not found',
                'message' => 'This page has been removed.'
            ]
        ];
    }

    public function http404Action() : array {
        $this->response->setStatusCode(404);
        return [
            'error' => [
                'code' => 404,
                'title' => 'Oops! Page not found.',
                'message' => 'We can\'t find the page you\'re looking for.'
            ]
        ];
    }

    public function http500Action( $exception ) : array {
        $this->response->setStatusCode(500);
        $this->lastError = $exception->getMessage();
        return [
            'error' => [
                'code' => 500,
                'title' => 'Oops! Something went wrong',
                'message' => $exception ?
                    (di()->get('dev') ? ($exception->getMessage() . "\n\n" . $exception->getTraceAsString()) : $exception->getCode())
                    : ''
            ]
        ];
    }


    public function http302Action(string $redirect) : array {
        $this->response->setStatusCode(302);
        $this->response->redirect($redirect, true, 302);
        return [];
    }




    /**
     * Sample anti-bot implementation
     */
    public function checkAction() {
        # @TODO - implement a bad-urls list
        $url = $this->request->getURI();
        if ($url == '/.env' || Str::endsWith($url, '.php')) {
            # @TODO - implement an ip-ban mechanism
            $this->dispatcher->forward([
                'action' => 'forbidden'
            ]);
        } else {
            $this->dispatcher->forward([
                'action' => 'http404'
            ]);
        }
    }


    /**
     * @see Core\OutputController::beforeExecuteRoute
     * @see checkAction
     */
    public function forbiddenAction() : array {
        $this->view->setMainView('_base/error/http500');
        $this->response->setStatusCode(403);
        return [
            'error' => [
                'code' => 403,
                'title' => 'Bad boi!',
                'message' => 'bad, bad, bad!'
            ],
            'hideBackButton' => true
        ];
    }
}
