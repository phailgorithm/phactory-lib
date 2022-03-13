<?php namespace Core;

use Phalcon\Mvc\Dispatcher;
use JsonSchema\Validator;

class TrackerController extends SessionController {


    public function afterExecuteRoute(Dispatcher $dispatcher) {
        $this->view->disable();
        parent::afterExecuteRoute($dispatcher);

        // @TRACK Where the actual data gets pushed
        $this->di->getTracker()->track(array_merge(
            $this->getTrackingData(),
            $this->view->getParamsToView()['view']
        ));

        $this->response->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

        $reply = ($this->di->get('dev')) ? [
            'ok' => 1,
            'tracker' => $this->view->getParamsToView()['view'],
            'session' => $this->view->getParamsToView()['session'],
            'data' => $this->getTrackingData()
        ] : ['status' => 200 ];

        $this->response->setHeader('Content-Length', strlen(json_encode($reply)));
        $this->response->setJsonContent($reply);


    }


    public function trackAction() : array {
        $data = $this->request->getJsonRawBody(false);
        if (empty($data)) {
            return [];
        }

        $data = array_filter_recursive( json_decode(json_encode($data), true), function ($v) {
            return !empty($v) || is_numeric($v) || is_bool($v);
        });
        if (!empty($data['local']['ref'])) {
            $data['local']['refhost'] = parse_url($data['local']['ref'], PHP_URL_HOST);
        }
        return $data;
        // // array_walk_recursive($data, 'array_filter');
        // $validator = new Validator;
        // $validator->validate($data, (object)['$ref' => 'file:///base/tracker/event.schema.json' ]);

        // # @PATCH
        // if ($validator->isValid()) {
        //     # removal of empty arrays, preserving zero-valued fields
        //     return array_filter_recursive( json_decode(json_encode($data), true), function ($v) {
        //         return !empty($v) || is_numeric($v) || is_bool($v);
        //     });
        // } else {
        //     $this->dispatcher->forward([
        //         'namespace' => 'Core',
        //         'controller' => 'Error',
        //         'action' => 'forbidden'
        //     ]);
        //     return [];
        // }
    }
}
