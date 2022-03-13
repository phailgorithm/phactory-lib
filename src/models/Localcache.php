<?php namespace Phailgorithm\PhactoryLib\Core;

class Localcache extends Cache {

    protected static $instance;
    protected static $cache = array();
    protected static $miss  = array();
    protected static $hit   = array();

    public static function di() {
        return di()->getLocalcache();
    }


}