<?php namespace Core\Geo;

use Core\Geo;
// use Phalcon\Mvc\Model;
// use Phalcon\Db\Column;
// use Phalcon\Mvc\Model\MetaData;
// use Phalcon\Mvc\Model\Resultset\Simple as Resultset;
use Carbon\Carbon;
use Cache;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Incremental\Input;
use Incremental\Route;


class City extends \Core\Geo implements Arrayable {

    const TABLE = 'geox';

    protected $id, $population, $population_rank, $timezone_offset_code, $timezone_raw_offset, $country_code, $region_id, $country_id, $lat, $lng, $coords, $nk;


    public function __construct(array $data) {
        parent::__construct($data);
        if (is_string($this->nk)) {
            $this->nk = json_decode($this->nk, true);
        }

        if (is_string($this->coords)) {
            $this->coords = trim($this->coords, '()');
            $this->coords = explode(",", $this->coords);
            $this->coords = [
                floatval($this->coords[0]),
                floatval($this->coords[1])
            ];
        } else if (!is_array($this->coords)) {
            $this->coords = [ 0.0 , 0.0 ];
        }
    }

    public static function fromInputString(string $id) {
        return self::findOne( intval( str_replace('c.', '', $id) ) );
    }


    /**
     * Shortcut to lookup for objects in db by key and throw exception if not found
     *
     * @return static
     */
    public static function getInstance(string $value, string $locale = null) : self {
        $model = static::cachedQuery('city:' . $value, -1,
            'SELECT * FROM '. static::TABLE .' WHERE id = ( SELECT geo_city FROM geo_city_name AS A INNER JOIN language AS L ON (L.id = A.language) WHERE A.key = ? AND L.locale_code = ?)',
            [ $value, $locale ?? di()->getLocale() ]
        );
        if (!$model->count()) {
            throw new \Core\Exception\NotFound;
        }
        return $model->first();
    }




    /**
     * @return Collection
     */
    public static function getTop(int $limit = 25) : Collection {
        return static::cachedQuery('top-'.$limit, 10,
            'SELECT * FROM ( SELECT * FROM ' . static::TABLE . ' WHERE country_id = (SELECT id FROM geo_country WHERE code_iso_3166 = ?) ORDER BY population DESC LIMIT ? ) as top_cities ORDER BY k ASC',
            [ 'it', $limit ]
        );
    }




    public static function fromRegionID(int $id, int $limit = 10) : array {
        $cities = [];
        $query = static::db()->query('SELECT * FROM ' . static::TABLE . ' WHERE region_id = ? ORDER BY population DESC LIMIT ?', [$id, $limit]);
        while ($city = $query->fetch(\PDO::FETCH_ASSOC)) {
            $cities[] = new static($city);
        }

        return $cities;
    }

    public static function fromCountryID(int $id, int $limit = 10) : array {
        $cities = [];
        $query = static::db()->query('SELECT * FROM ' . static::TABLE . ' WHERE country_id = ? ORDER BY population DESC LIMIT ?', [$id, $limit]);
        while ($city = $query->fetch(\PDO::FETCH_ASSOC)) {
            $cities[] = new static($city);
        }

        return $cities;
    }

    /**
     * Gets the closest city to input coords
     *
     * @return City
     */
    public static function findClosest(float $lat, float $lng) : City {
        $q = static::db()->query(
            'SELECT * FROM (SELECT * FROM ' .static::TABLE. ' AS n ORDER BY coords <-> POINT(?,?) LIMIT 10 ) AS cities ORDER BY population DESC LIMIT 1',
            [ $lat, $lng ]
        );
        $data = $q->fetch(\PDO::FETCH_ASSOC);
        return new static($data);
    }

    /**
     * @return int
     */
    public function getId() : int {
        return $this->id;
    }

    /**
     * @return float
     */
    public function getLat() {
        return $this->getCoords()[0];
    }

    /**
     * @return float
     */
    public function getLng() {
        return $this->getCoords()[1];
    }

    /**
     * @return int
     */
    public function getRank() : int {
        return $this->population;
    }

    /**
     * @return int
     */
    public function getPopulation() : int {
        return $this->population;
    }

    /**
     * @return array
     */
    public function getCoords() {
        return $this->coords;
    }


    public function getCountry() {
        if (!isset($this->_country)) {
            $this->_country = Country::findOne($this->country_id);
        }
        return $this->_country;
    }

    public function getRegion() {
        if (!isset($this->_region)) {
            $this->_region = Region::findOne($this->region_id);
        }
        return $this->_region;
    }

    /**
     */
    public function getCountryTop10() : Collection {
        $q = static::db()->query('SELECT id FROM ' .static::TABLE. ' WHERE country_id = ? AND population_rank <= 10', [ $this->country_id ]);

        $result = new Collection;
        while ($data = $q->fetch(\PDO::FETCH_ASSOC) ) {
            $result[] = $data['id'];
        }
        return $result;

    }


    /**
     * @return string
     */
    public function getCountryCode() : string {
        switch ($this->country_code) {
            case 'gb':
                return 'uk';
            default:
                return $this->country_code;
        }
    }


    /**
     * @return string
     */
    public function ld() : string {
        if (empty($this->tld)) {
            $this->tld = $this->getCountryCode();
        }
        return $this->tld;
    }

    /**
     * How many seconds are missing to the end of the day in this timezone
     *
     * @return int
     */
    public function getDayTTL( int $now = null ) : int {
        $now = is_null($now) ? Carbon::now($this->getTimezone()) : Carbon::createFromTimestamp($now);
        return $now->copy()->endOfDay()->diffInSeconds($now);
    }


    /**
     * @return string
     *
     * @throws \Exception no country timezone found
     */
    public function getTimezone() : string {
        return $this->timezone_offset_code;
    }



    /**
     *
     */
    public function getNearbyCities() : Collection {
        $sql = "
            SELECT *,
                    degrees(ST_Azimuth( geometry_coords::geography,ST_SetSRID(ST_MakePoint(:lat,:lng),4326) )) AS azimuth,
                    ST_Distance(geometry_coords::geography,ST_SetSRID(ST_MakePoint(:lat,:lng),4326)::geography, True) AS distance
            FROM " . static::TABLE . "
            WHERE id != :id
            AND country_id = :country_id
            AND   population >= :minPopulation

            ORDER BY ST_Distance(geometry_coords::geography,ST_SetSRID(ST_MakePoint(:lat,:lng),4326)::geography, True)
            ASC LIMIT 60
            ";

        return self::cachedQuery(sprintf('nearby-city-%d',$this->id), -1, $sql, [
            'id'            => $this->id,
            'country_id'    => $this->country_id,
            'lat'           => $this->getLat(),
            'lng'           => $this->getLng(),
            'minPopulation' => conf()->citiesMinPopulation
        ]);
    }




    public function getNearby(int $limit = 1, string $criteria = null, $excludeIds = []) {
        $excludeIds[] = $this->id;
        $excludeIds = array_unique($excludeIds);

        $params = [
            'lat'           => $this->lat,
            'lng'           => $this->lng,
            'limit'         => $limit ?? 1
        ];

        $sql = "
            SELECT C.* FROM geo_city AS C
            %s
            WHERE C.coords <-> POINT(:lat, :lng) < 100
            AND ROUND( (2 * 3961 * asin(sqrt((sin(radians((:lat - C.coords[0]) / 2))) ^ 2 + cos(radians(C.coords[0])) * cos(radians(:lat)) * (sin(radians((:lng - C.coords[1]) / 2))) ^ 2) ) )::numeric ,2) < :distance";

        if (count($excludeIds) > 0) {
            $sql .= " AND c.id NOT IN (". implode(',', $excludeIds) .")";
        }

        switch ($criteria) {
            case 'plane':
                $params['distance'] = 1000;
                // @INFO backported logic but not the best solution to select the cities with airports
                $sql = sprintf($sql, 'LEFT JOIN city_spider AS CS ON (CS.city_id = C.id) LEFT JOIN spider AS S ON (CS.spider_id = S.id)');
                $sql .= " AND S.code = 'kayak' ORDER BY C.coords <-> POINT(:lat, :lng) ASC";
                break;

            /*case 'rentalcar':
                $params['distance'] = 50;
                $params['tld'] = json_encode([$this->getTld()]);
                $sql = sprintf($sql, '');
                $sql .= "AND C.rentalcar_active_tlds::jsonb @> :tld::jsonb ORDER BY C.population DESC";
                break;*/

            default:
                $params['distance'] = 30;
                $sql = sprintf($sql, '');
                $sql .= " ORDER BY C.population DESC";
                break;
        }

        $sql .= " LIMIT :limit";

        $q = static::db()->query($sql, $params);

        $return = new Collection;

        while (($data = $q->fetch(\PDO::FETCH_ASSOC)) && $return->count() < $limit) {
            $entry = new self($data);

            if (!$return->contains($entry)) {
                $return[] = $entry;
            }
        }

        return $return;
    }

   /**
    * Retrieved a limited list of nearby cities that have valid routes for a specific transport having as depature this city
    *
    * @param int $limit - max number of resutls
    * @param string $transport - transport route type
    * @return Collection
    */
    public function getNearbyWithValidRoutes(int $limit = 1, string $transport) : Collection {
        $nearbys = $this->getNearby($transport != 'plane' ? 50 : 300, $transport, [$this->getId()]);
        $nearbyIds = $nearbys
                        ->map(function($c){ return 'c.'.$c->getId(); })
                        ->implode("','");

        # get all the TOs routes starting from this city
        $sql = sprintf(
            "SELECT
                -- incremental_route_id,
                -- input_from,
                DISTINCT(input_to)
                -- transport
            FROM
                incremental_network_normal_%s
                AS A INNER JOIN incremental_route_%s AS B ON ( A.incremental_route_id = B.id )
            WHERE
                input_from = :from AND
                input_to IN ('%s') AND
                transport = :transport limit 50;",
            $this->getCountry()->getCode(),
            $this->getCountry()->getCode(),
            $nearbyIds
        );

        $params = [
            'from' => 'c.'.$this->getId(),
            'transport' => $transport
        ];

        $q = static::db()->query($sql, $params);

        $validRouteTos = new Collection;
        while (($data = $q->fetch(\PDO::FETCH_ASSOC))) {
            $validRouteTos[] = $data['input_to'];
        }

        # get valid nearbys with valid routes in the same order
        $nearbyValidTos = new Collection;
        foreach ($nearbys as $nearby) {
            if ($validRouteTos->contains('c.'.$nearby->getId())) {
                $nearbyValidTos[] = $nearby;
            }

            # we've got the requested number
            if ($nearbyValidTos->count() >= $limit) {
                break;
            }
        }

        return $nearbyValidTos;
    }

    public function getNearbyWithRental(int $limit = 1, int $maxDistance = 500) : Collection {
        $sql = "SELECT C.*
                FROM geo_city AS C
                WHERE C.id != :self_id AND
                    ROUND(
                        (2 * 3961 * asin(sqrt(
                            (sin(radians((:lat - C.coords[0]) / 2))) ^ 2 +
                            cos(radians(C.coords[0])) * cos(radians(:lat)) * (sin(radians((:lng - C.coords[1]) / 2))) ^ 2
                        ) ))::numeric, 2
                    ) < :distance AND
                    C.rentalcar_active_tlds::jsonb @> :tld
                    ORDER BY C.coords <-> POINT(:lat,:lng)
                    ASC LIMIT :limit";

        $params = array(
            'self_id'       => $this->id,
            'lat'           => $this->lat,
            'lng'           => $this->lng,
            'limit'         => $limit,
            'distance'      => $maxDistance,
            'tld'           => json_encode([app()->getTld()])
        );

        $q = static::db()->query($sql, $params);

        $return = new Collection;
        while ($data = $q->fetch(\PDO::FETCH_ASSOC)) {
            $return[] = new self($data);
        }

        return $return;
    }


    /**
     * Distance from coordinates
     *
     * @param float  $lat
     * @param float  $lng
     * @param char   $unit  K for Km, N for Nodes, Empty for Miles. Default K
     *
     * @return float
     */
    public function distanceFromPoint($lat, $lng, $unit = 'K') {
        return self::geoDistance($this->getLat(), $this->getLng(), $lat, $lng, $unit);
    }

    public static function geoDistance($lat1, $lon1, $lat2, $lon2, $unit = 'K') {

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        $miles = is_nan($miles) ? 0 : $miles;

        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }

    /**
     * Distance from another City object
     *
     * @param City   $city
     * @param char   $unit  K for Km, N for Nodes, Empty for Miles. Default K
     *
     * @return float
     */
    public function distance(City $city, $unit = 'K') {
        return $this->distanceFromPoint($city->getLat(), $city->getLng(), $unit);
    }


    public function getViewLabel() {
        return !!$this->customName ? $this->customName :  _trans_named(['names' => $this->getNames()]); //@TODO
    }

    /**
     * Copied from Place\City - @TODO
     *
     * @return array
     */
    public function toArray($columns = array()) : array {
        $return = array(
            'id' => $this->id,
            'coords'=> $this->getCoords(),
            'name' => $this->getName(),
            'key' => $this->getKey(),
            'timezone' => $this->getTimezone(),
            'population' => $this->population
        );

        foreach ($columns as $col) {
            switch ($col) {
                case 'k':
                    $return['k'] = $this->k;
                    break;

                case 'region':
                    $return['region'] = $this->getRegion()->toArray();
                    break;

                // case 'country':
                //     $return['country'] = $this->country->toArray();
                //     break;

                case 'stations':
                    foreach ($this->getStations() as $s) {
                       $return['stations'][$s->id] = $s->getName();
                    }
                    break;
            }
        }
        return $return;
    }

    public function toMetrics() : array {
        return array(
            // 'search_volume' => $this->search_volume
        );
    }


    /**
     * returns the encoded form as it would be for a Search\Input\City object
     *
     * @return string
     */
    public function __toString() : string {
        return sprintf('c.%s', $this->id);
    }


}