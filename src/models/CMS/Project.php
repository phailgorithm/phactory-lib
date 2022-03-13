<?php namespace Core\CMS;

use Illuminate\Support\Collection;

class Project extends \Core\CMS {

    protected function getSqlQuery() : string {
        return "SELECT DISTINCT code FROM project";
    }

    public static function list() : Collection {
        $object = new static();
        return $object->fetch()->items;
    }
}