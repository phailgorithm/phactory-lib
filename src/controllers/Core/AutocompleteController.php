<?php namespace Core;

use Phalcon\Mvc\Dispatcher;

/**
 */
class AutocompleteController extends OutputController {

    public function afterExecuteRoute(Dispatcher $dispatcher) {
        $this->response->setJsonContent( $dispatcher->getReturnedValue(), JSON_PRETTY_PRINT ) ;
        $dispatcher->setReturnedValue($this->response);
        parent::afterExecuteRoute($dispatcher);
    }


    public function acAction(string $locale, string $term) : array {
        $ac = new Autocomplete($term, $locale);
        $ac->exec();

        return array(
            'locale'=> $locale,
            'input' => $term,
            'data'  => $ac->toArray()
        );
    }
}