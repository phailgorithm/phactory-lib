<?php namespace Core\CMS;

use Illuminate\Support\Collection;

class Config extends \Core\CMS {

    protected function getSqlQuery() : string {
        $this->filters = [
            'project' => di()->getProject(),
            'env' => $_ENV['ENV']
        ];
        return
            "SELECT C.config FROM config AS C INNER JOIN project AS P ON P.id = C.project WHERE P.code = :project AND C.env = :env";
    }
}