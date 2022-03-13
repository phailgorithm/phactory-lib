<?php namespace Core;

use Model;

class Website extends Model
{

    public static function getSource() : string { return "website";  }

    public $id, $domain;


    public static function getInstance(string $domain = null) : ?self {
        $domain = is_null($domain) ? di()->getDomain() : $domain;
        return static::findOne($domain, 'domain');

    }
}