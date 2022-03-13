<?php namespace Search\Autocomplete\Backends;

use Requests;
use Search\Autocomplete;
use Search\Autocomplete\Match;

use Illuminate\Support\Collection;

class Google extends Autocomplete {

    const GOOGLE_FIELDS = 'address_component,adr_address,formatted_address,geometry,icon,name,place_id,plus_code,type,url,utc_offset';

    const GOOGLEMAPS_ENDPOINT   = 'https://maps.googleapis.com/maps/api/place';
    const GOOGLEMAPS_BASE_URL   = self::GOOGLEMAPS_ENDPOINT.'/autocomplete/json?types={{gatype}}&origin={{point}}&location={{point}}&key={{key}}&language={{locale}}&input={{input}}&sessiontoken={{session_token}}';
    const DETAILS_BASE_URL      = self::GOOGLEMAPS_ENDPOINT.'/details/json?fields={{fields}}&key={{key}}&language={{locale}}&placeid={{id}}&sessiontoken={{session_token}}';
    const GOOGLEMAPS_SEARCH_KEY = "g:ac:4:%s:%s:%s:%s";
    const GOOGLEMAPS_PLACE_KEY  = "g:place:4:%s:%s";
    const GOOGLEMAPS_SEARCH_TTL = 60 * 60 * 24 * 30 * 6; // 6 months;
    const GOOGLEMAPS_PLACE_TTL  = 60 * 60 * 24 * 30 * 12 * 2; // 2 years;

    private $redis, $sid;

    protected function getGAType( ) {
        return '(cities)';
    }

    public function __construct($input, $locale = null, Collection $matches = null) {
        parent::__construct($input, $locale, $matches);
        $this->redis  = app()->getRedisCache();
        $this->apiKey = app()->getConfig()->application->googlePlaceApiKey;
        $this->sid = 'nc:' . hash('ripemd256',
            @$_SERVER['HTTP_USER_AGENT']      . '::' .
            @$_SERVER['HTTP_ACCEPT']          . '::' .
            @$_SERVER['HTTP_ACCEPT_LANGUAGE'] . '::' .
            client_ip()
        );

        $this->tldCoords = app()->getConfig()->tldInfo->{app()->getTld()}->geoCenter;
    }

    public function match() : bool {
        $point = sprintf('%s,%s', $this->tldCoords->lat, $this->tldCoords->lng);

        $url = str_replace(
            array('{{key}}','{{input}}','{{locale}}','{{session_token}}','{{point}}', '{{gatype}}'),
            array($this->apiKey, $this->input, $this->locale, $this->sid, $point, $this->getGAType() ),
            self::GOOGLEMAPS_BASE_URL
        );

        $searchCacheKey = sprintf(self::GOOGLEMAPS_SEARCH_KEY, $this->locale, $point, $this->getGAType(), $this->input);

        $result = $this->redis->get($searchCacheKey);
        if ($result && $result === "false") {
            $result = false;
        }

        if ($result) {
            $data = json_decode($result);
        } else {
            $result = Requests::get($url)->body;
            $data = json_decode($result);

            $gqs = array();

            parse_str( parse_url($url, PHP_URL_QUERY), $gqs );
            unset($gqs['key']);

            app()->getFeLogger()->info('Request to Google Autocomplete Search', [
                'gainput'  => $gqs,
                'gaoutput' => [
                    'status' => $data->status,
                    'resultCount' => isset($data->predictions) ? count($data->predictions) : 0
                ]
            ]);
            if ($data->status == 'ZERO_RESULTS') {
                $ttl = self::GOOGLEMAPS_SEARCH_TTL / 2;
            } else if ($data->status == 'OVER_QUERY_LIMIT') {
                $ttl = 60;
            } else {
                $ttl = self::GOOGLEMAPS_SEARCH_TTL;
            }

            $this->redis->set( $searchCacheKey, $result, $ttl );
        }

        if ($data) {
            foreach ($data->predictions as $k => $p) {
                if ($place = $this->fetchPlace( $p->place_id )) {
                    $data->predictions[$k]->place_details = $place;
                    $this->matches[] = $this->transformPlace($place, $p->description);
                }
            }
        }

        return !!$this->matches->count();
    }

    public function transformPlace($place, $name = null) {

        foreach ($place->address_components as $d) {
            if (in_array('country',$d->types)) {
                $region_code = strtolower($d->short_name);
                break;
            }
        }

        if (!isset($region_code)) {
            app()->getFeLogger()->warning('Region Code not found for google data', [
                'googledata' => (array) $place
            ]);
            $region_code = 'us';
        }
        try {
            $country = \Place\Country::findByRegionCode($region_code);
        } catch (\Exception $e) {
            $country = \Place\Country::findByRegionCode('us');
        }

        return new Match(
            'city',
            sprintf('g.%s.%s', $this->locale, $place->place_id),
            is_null($name) ? $place->formatted_address : $name,
            $place->geometry->location->lat,
            $place->geometry->location->lng,
            0,
            $country->getCode(),
            $this
        );
    }

    public function fetchPlace(string $placeId) {
        $url = str_replace(
            array('{{fields}}', '{{key}}','{{id}}','{{locale}}','{{session_token}}'),
            array(self::GOOGLE_FIELDS, $this->apiKey, $placeId, $this->locale, $this->sid),
            self::DETAILS_BASE_URL
        );

        # this key must have same format as Search\Input->getPlace()
        $placeCacheKey = sprintf(self::GOOGLEMAPS_PLACE_KEY, $this->locale, $placeId);

        $this->data = $this->redis->get($placeCacheKey);
        if (!$this->data) {
            $this->data = $this->fetch($url);
            if ($this->data) {
                $this->redis->set($placeCacheKey, _zip($this->data), self::GOOGLEMAPS_PLACE_TTL);
            }
        } else {
            $this->data = _unzip($this->data);
            if (isset($data->result) && $data->result == 'OK') {
                return $data->result;
            }
        }

        return $this->data;
    }

    public function fetch($url) {
        try {
            $data = Requests::get($url);
            $data = json_decode($data->body);

            $gqs = array();

            parse_str( parse_url($url, PHP_URL_QUERY), $gqs );
            unset($gqs['key']);

            app()->getFeLogger()->info('Request to Google Autocomplete Place', [
                'gainput'  => $gqs,
                'gaoutput' => [
                    'status' => $data->status,
                    // 'resultCount' => isset($data->predictions) ? count($data->predictions) : 0
                ]
            ]);

            if ($data && $data->status == 'OK') {
                return $data->result;
            }
        } catch (\Exception $e) {
            // app()->getFeLogger()->warning()
            // debug($e->getMessage());
            $data = null;
        }

        return false;
    }
}