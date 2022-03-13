<?php namespace Core\Directus;

use WpOrg\Requests\Requests;
use Illuminate\Support\Arr;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Controller;

use Core\Exception\NotFound;


class FileController extends \Core\OutputController {

    public function fetchAction(string $file) {

        $url = $this->di->getTwig()->renderTemplate('{{ baseUrl }}/assets/{{ file }}?access_token={{ token }}', [
            'file' => $file,
            'token' => $_ENV['DIRECTUS_TOKEN'],
            'baseUrl' => $_ENV['DIRECTUS_BASE_URI']
        ]);
        $x = Requests::get($url);

        if ($x->status_code == 200) {
            $this->response->setContentType(
                $x->headers['content-type']
            );
            $this->response->setContentLength(
                $x->headers['content-length']
            );

            return $this->response->setContent($x->body);
        }
        $this->response->setStatusCode(404);
        return $this->response->setContent('not-found');
    }

}



