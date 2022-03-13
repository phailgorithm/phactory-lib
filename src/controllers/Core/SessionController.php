<?php namespace Core;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher\Exception as DispatchException;

use Carbon\Carbon;

use Illuminate\Support\Collection;
use Phalcon\Session\Adapter\Redis as Session;


class SessionController extends OutputController {

    const ALLOWED_QS = [];

    protected $session, $isNewSession;

    /**
     * @return array
     */
    public function getSession() : array {
        $data = array_filter(
            $_SESSION,
            function( $v, string $k ) {
                return strpos($k, '_PHCOOKIE') === false && $k != 'PHPDEBUGBAR_STACK_DATA' && !empty($v);
            },
            ARRAY_FILTER_USE_BOTH
        );
        $output = array();
        foreach ($data as $k => $v) {
            $output [
                preg_replace('/^' . $this->session->getName() . '#/', '', $k)
            ] = $v;
        }
        return $output;
    }

    /**
     * Before each tracking URL, common things as loading session
     */
    public function beforeExecuteRoute(Dispatcher $dispatcher) {
        parent::beforeExecuteRoute($dispatcher);
        $this->session = $this->di->getSession();
        $this->session->start();

        if ($this->session->has('start')) {
            $this->isNewSession = false;
            $this->session->hits++;
        } else {
            $this->isNewSession = true;
            $this->session->set('start', time());
            $this->session->hits = 1;
        }
    }

    /**
     * After each tracking URL, common things as saving session, sending out the pixel binary
     */
    public function afterExecuteRoute(Dispatcher $dispatcher) {
        $this->view->setVar('session', $this->getSession() );
        parent::afterExecuteRoute($dispatcher);
    }



    protected function getTrackingData() : array {

        $this->session->set('tdelta', $this->session->start ? (time() - $this->session->start) : null);

        return (array(
            '@ts'        => time(),
            // '@cookie'    => $this->isNewSession ? $_COOKIE : null,
            '@newuser'   => boolval($this->isNewSession),
            '@session'   => $this->getSession(),
            '@sid'       => $this->di->getSession()->getId(),
            'ua' => $this->request->getHeader('User-Agent'),
            // '@url'       => array_filter([
            //     'ref'   => $this->request->get('ref', 'string' ),
            //     'path'  => $_SERVER['HTTP_HOST'] . $this->trackUrl,
            //     'query' => $this->trackQuery ]),
            // '@query'    => array_filter($_GET),
            // '@browser'  => array_filter(
            //     get_browser(null, true), function( $v, string $k ) {
            //             return strpos($k, 'browser_name_') === false && !empty($v);
            //         },
            //         ARRAY_FILTER_USE_BOTH
            //     )
        ));


    }

}
