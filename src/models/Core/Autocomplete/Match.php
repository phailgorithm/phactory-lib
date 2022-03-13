<?php namespace Core\Autocomplete;

use Core\Autocomplete\Backend;
use Illuminate\Contracts\Support\Arrayable;

class Match implements Arrayable {

    protected $id, $data, $backend;

    public function __construct(string $id, array $data = array(), Backend $backend = null) {
        $this->id       = $id;
        $this->data     = $data;
        $this->backend  = $backend;
    }

    public function getId() : string {
        return $this->id;
    }

    public function toArray() : array {
        return array(
            'id'    => $this->id,
            'data'  => $this->data,
            'be'    => $this->backend->getName()
        );
    }
}