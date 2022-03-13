<?php namespace Phailgorithm\PhactoryLib\Model;

use Phailgorithm\PhactoryLib\Exception\NotFound;
use Phalcon\Mvc\Model\Resultset\Simple as Resultset;
use Illuminate\Support\Collection;

class Redirect extends \Model {

    public static function getSource() : string { return "redirect";  }

    public $url_source;

    public $url_target;

    public $status;


    public static function getRedirect(string $url = null) : self {
        $host =  (isset($_ENV['PROJECT_DOMAIN'])) ? $_ENV['PROJECT_DOMAIN'] : di()->getDomain();
        $url = is_null($url) ? ($host. $_SERVER['REQUEST_URI']) : $url;
        $data = static::query(
            "SELECT url_target, status FROM redirect WHERE url_source = ?",
            [ $url ]
        );
        if (!$data->count()) {
            throw new NotFound;
        }
        return new self( $data->first()->toArray() );
    }
}