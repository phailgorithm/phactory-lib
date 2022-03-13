<?php namespace Core;

use Timing, Exception;
use Illuminate\Support\Arr;

use Phalcon\Crypt;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Http\Response;

/**
 */
class ClickoutController extends SessionController implements Tracked {

    private $redirectUrl;

    public function beforeExecuteRoute(Dispatcher $dispatcher) {
        // $this->trackHandler = $handler;
        parent::beforeExecuteRoute($dispatcher);
    }


    public function afterExecuteRoute(Dispatcher $dispatcher) {

        if (!!$this->redirectUrl) {
            $this->response->redirect($this->redirectUrl, true, 301);
            $dispatcher->setReturnedValue($this->response);
        }

        parent::afterExecuteRoute($dispatcher);
    }


    public function getTrackingData() : array {
        // d($this->trackedData);
        return array_merge(parent::getTrackingData(), array(
            '@url'       => array_filter([
                'ref'   => $this->request->getHTTPReferer(),
                'path'  => $this->request->getURI() ]),

            '@handler'  => $this->route,
            '@data'     => $this->trackedData,
            '@clickout' => array(
                'url' => $this->redirectUrl,
                // 'data' => $this->trackedData
            )
        ));
    }


    public function redirectAction(string $safe64Data, string $safe64Url) {
        $this->redirectUrl = $this->crypt->decryptBase64( strtr($safe64Url, '._-', '+/=') , getenv('TRACKING_CRYPT_KEY'));
        $this->trackedData = msgpack_unpack($this->crypt->decryptBase64( strtr($safe64Data, '._-', '+/=') , getenv('TRACKING_CRYPT_KEY')));
    }
}