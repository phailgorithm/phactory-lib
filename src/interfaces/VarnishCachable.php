<?php

/**
 *
 */
interface VarnishCachable {

    const VC_HEADER_PREFIX = 'X-PH-VC';
    /**
     *
     * @return int
     */
    function getTTL() : int;

    /**
     *
     * @return int
     */
    function getGrace() : int;



}
