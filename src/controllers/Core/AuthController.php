<?php namespace Core;

use Opauth;
use Gregwar\Captcha\{PhraseBuilder, CaptchaBuilder};
use YoHang88\LetterAvatar\LetterAvatar;
use Core\Exception\NotFound;
// use Illuminate\Support\Arr;
use Phalcon\Mvc\Dispatcher;
// use Phalcon\Mvc\Controller;
// use Illuminate\Support\Collection;

class AuthController extends OutputController {

    protected $userClass = 'Core\User';

    public function beforeExecuteRoute(Dispatcher $dispatcher) {
        parent::beforeExecuteRoute($dispatcher);
        $this->outputAddMetadata = false;
        $this->view->disable();
    }

    /**
     * Returns the url where the user gets redirected in case of successful login.
     * To be overridden by children classes
     *
     * @return string
     */
    public static function getRedirectURL() : string {
        return '/';
    }


    public function loginAction(string $strategy) : array {
        switch ($strategy) {

            case 'signup':
                $rawBody = $this->request->getJsonRawBody(true);
                try {
                    $user = $this->userClass::findOne($rawBody['username'] ,'email');
                    if (!!$user) {
                        return ['error' => 'user_exists'];
                    }
                } catch (NotFound $e) {
                    $this->userClass::create($rawBody['name'], $rawBody['username'], $rawBody['password']);
                    return [
                        'status' => 1
                    ];
                }


            case 'up':
                $rawBody = $this->request->getJsonRawBody(true);
                $user = $this->userClass::findOne($rawBody['username'] ,'email');
                if (password_verify($rawBody['password'], $user->password)) {
                    $avatar = (new LetterAvatar($user->name));
                    session()->start();
                    session()->set('user', [
                        'info' => [
                            'name' => $user->name,
                            'email' => $user->email,
                            'image' => (string) $avatar
                        ]
                    ]);


                    return [
                        'status' => 1
                    ];
                }
                return [
                    'error' => 1
                ];

            default:
                $Opauth = new Opauth( conf()->auth->toArray() );
                return [];
        }
    }


    // public function indexAction() : array {
    //     $Opauth = new Opauth( conf()->auth->toArray() );
    // }

    public function callbackAction(string $strategy) {
        error_reporting(E_ERROR);
        $Opauth = new Opauth( array_merge( conf()->auth->toArray(), [
            'request_uri' => conf()->auth->path . $strategy . '/oauth2callback?' . $_SERVER['QUERY_STRING'],
            'callback_url' => conf()->auth->path . $strategy . '/done?'. $_SERVER['QUERY_STRING'],

        ] ) );
    }

    /**
     */
    protected function validate(string $strategy) : ?array {
        error_reporting(E_ERROR);
        $Opauth = new Opauth( array_merge(conf()->auth->toArray(), [
                'request_uri' => sprintf('/%s/callback?%s', $strategy, $_SERVER['QUERY_STRING']) ] ) , false);
        $response = json_decode(base64_decode( $_POST['opauth'] ), true);

        if (!!$response && array_key_exists('error', $response)) {
            return null;
        } else {
            if (empty($response['auth']) || empty($response['timestamp']) || empty($response['signature']) || empty($response['auth']['provider']) || empty($response['auth']['uid'])) {
                return null;
            } elseif (!$Opauth->validate(sha1(print_r($response['auth'], true)), $response['timestamp'], $response['signature'], $reason)) {
                return null;
            }
        }
        return $response;
    }


    /**
     *
     */
    public function authAction(string $strategy) {
        $validation = $this->validate($strategy);
        if (!$validation) {
            die('login failed');
        }
        else{
            session()->start();
            session()->set('user', [
                'info' => $validation['auth']['info'],
                'raw'  => $validation['auth']['raw'],
            ]);
            $this->userClass::upsert($validation['auth']['info']['name'], $validation['auth']['info']['email'], $validation['auth']['raw']);

            $this->response->redirect(static::getRedirectURL(), true, 302);
            $this->dispatcher->setReturnedValue($this->response);
            return [];
        }
    }


    /**
     *
     */
    public function checkAction() : array {
        session()->start();
        return array(
            'logged' => !!session()->get('user')
        );
    }



}

