<?php namespace Search\Autocomplete\Backends;

class GoogleGeocode extends Google {
    protected function getGAType( ) {
        return 'geocode';
    }
}