<?php namespace Core;

class UserController extends OutputController {


    /**
     *
     */
    public function signupAction() : array {
        // $this->view->disable();

        // $form = \Features\Form::getInstance('testform');
        // session()->set('captcha', $form->setCaptcha());


        // $builder = new CaptchaBuilder(null, new PhraseBuilder(rand(4,6), '0123456789') );
        // $builder->build();
        return array(
            // 'captcha' => $builder->inline(),
            // 'phrase' => $form->setCaptcha(), //$builder->getPhrase()
        );

    }

    public function logoutAction() {
        sleep(1);
        session()->start();
        session()->set('user', null);
        $this->response->redirect('/', true, 302);
        $this->dispatcher->setReturnedValue($this->response);

    }




    public function signinAction() : array {
        return [
            'actions' => [
                'google' => [
                    'href' => '/core/auth/google'
                ]
            ]
        ];
    }

}

