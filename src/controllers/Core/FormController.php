<?php namespace Core;

use Illuminate\Support\Arr;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Controller;
use Illuminate\Support\Collection;





class FormController extends OutputController {

    public function submitAction() : array {
        $this->view->disable();
        $this->outputAddMetadata = false;
        session()->start();
        $form = \Features\Form::getInstance($this->request->get('formid'));



// d($this->request->getUploadedFiles(), $form);
        $input = array_merge(
            $this->request->getPost(),
            $_FILES
        );

        $valid = $form->validate( $input );

        if ($valid->count() > 0) {
            $messages = array();
            foreach ($valid as $m) {
                $messages[] = [
                    'type' => $m->getType(),
                    'message' => (string) $m
                ];
            }
            return [
                'status' => 422,
                'messages' => $messages,
                'post' => $_POST
            ];
        }
        try {
            $res = $form->saveData($_POST);
        } catch (\Throwable $e) {

            di()->getLog()->error($e->getMessage(), [
                'index' => $index,
                'folder' => $folder,
                'exception' => $e
            ]);

            return [
                'status' => 501,
                'message' => $e->getMessage()
            ];
        }
        return [
            'status' => 200,
        ];

    }
}

