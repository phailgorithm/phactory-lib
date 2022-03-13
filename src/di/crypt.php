<?php
use Phalcon\Crypt;

return function () {

    return new Crypt('aes-256-ctr', true);

};
