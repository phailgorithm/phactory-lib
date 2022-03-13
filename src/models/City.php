<?php namespace Phailgorithm\PhactoryLib\Model;

use Phailgorithm\PhactoryLib\Model;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Incremental\Input;
use Incremental\Route;


class City extends Model implements Arrayable {

    public static function getSource() : string { return sprintf('%s_city', di()->getProject()); }

    protected
        $id,
        $geo_city,
        $key,
        $name,
        $coords,
        $population;


    public function __construct(array $data = array()) {
        parent::__construct($data);
        if (isset($this->coords)) {
            $this->coords = explode(',', trim($this->coords, '()'));
            $this->coords = [
                'lat' => $this->coords[1],
                'lng' => $this->coords[0]
            ];
        }
    }




    /**
     * @return Self
     */
    public static function getInstanceById(int $id, int $ttl = 604800)  {
        $locale = di()->getLocale();
        return static::cachedQuery(
            $key = sprintf('city:%d', $id),
            $ttl,
            $query = sprintf('SELECT A.id, B.key, B.name, A.coords::point AS coords, A.population
                FROM %s AS A INNER JOIN %s_name AS B ON (A.id = B.geo_city)
                WHERE A.id = ?
                AND B.language = (SELECT id FROM language WHERE locale_code = ?)
                ', static::getSource(), static::getSource() ),
            $args = [
                $id,
                $locale
            ]
        )->first();
    }




    public function calcDistance($lat, $lon, $unit = 'K') {
        $lat1 = $this->getLat();
        $lat2 = $lat;
        $lon1 = $this->getLng();
        $lon2 = $lon;

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
     * @return Collection
     */
    public static function getTopCitiesInCountry(string $country, int $limit = 25) : Collection {
        $locale = di()->getLocale();
        return static::cachedQuery(
            $key = sprintf('topcities-country:%s:%d', $country, $limit),
            $ttl = 60*60*24*7,
            $query = sprintf('SELECT A.id, B.key, B.name, A.coords::point AS coords, A.population
                FROM %s AS A INNER JOIN %s_name AS B ON (A.id = B.geo_city)
                WHERE A.country = (SELECT id FROM geo_country WHERE code_iso_3166 = ?)
                AND B.language = (SELECT id FROM language WHERE locale_code = ?)
                ORDER BY population DESC
                LIMIT ?', static::getSource(), static::getSource()),
            $args = [
                $country,
                $locale,
                $limit
            ]
        );
    }

    /**
     * Shortcut to lookup for objects in db by key and throw exception if not found
     *
     * @return static
     */
    public static function getInstanceByKey(string $cityKey, string $locale = null) : self {
        $locale = $locale ?: di()->getLocale();
        $model = static::cachedQuery(
            $key = sprintf('c:%s', $cityKey),
            $ttl = 60*60*24*7,
            $query = sprintf('SELECT A.id, B.key, B.name, A.coords::point AS coords, A.population
                FROM %s AS A INNER JOIN %s_name AS B ON (A.id = B.geo_city)
                AND B.language = (SELECT id FROM language WHERE locale_code = ?)
                AND B.key = ?', static::getSource(), static::getSource()),
            $args = [
                $locale,
                $cityKey
            ]
        );
        if (!$model->count()) {
            throw new \Core\Exception\NotFound("locale/key: {$locale}/{$cityKey}");
        }
        return $model->first();
    }


    /**
     * @return string
     */
    public function getName($locale = null) : string {
        return $this->name;
    }

    public function getLat() {
        return $this->coords['lat'];
    }

    public function getLng() {
        return $this->coords['lng'];
    }
    public function getKey() : string {
        return $this->key;
    }
    public function getGeoCityId() {
        return $this->geo_city;
    }


    public static function getClosestToPoint(float $lat, float $lng) : self {
        $ttl = 60*60*24;
        $sql = "
            SELECT id, geo_city, key, name, coords::point AS coords, population,
                    ST_Distance(
                        ST_SetSRID( coords, 4326)
                        ,ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography,
                        True
                    ) AS distance
            FROM ". static::getSource() ."
            ORDER BY ST_Distance(ST_SetSRID( coords, 4326),ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography, True)
            ASC LIMIT 1
            ";
        $model = self::cachedQuery(sprintf('closest:%s,%s', $lat,$lng), $ttl, $sql, [
            'lat'           => $lat,
            'lng'           => $lng
        ]);

        if (!$model->count()) {
            throw new \Core\Exception\NotFound;
        }
        return $model->first();


    }

    /**
     *
     */
    public function getNearbyCities(int $limit = 60, array $filters = array()) : Collection {
        $ttl = 60*60*24;
        $sql = "
            SELECT id, geo_city, key, name, coords::point AS coords, population,
                    ST_Distance(
                        ST_SetSRID( coords, 4326)
                        ,ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography,
                        True
                    ) AS distance
            FROM ". static::getSource() ."
            WHERE id != :id";

        $filtersData = array();
        foreach ($filters as $n => $filter) {
            $e = explode(' ', $filter, 3);
            $sql .= sprintf(' AND %s %s :filter%d', $e[0], $e[1], $n);
            $filtersData['filter'.$n] = $e[2];
        }

        $sql .= " ORDER BY ST_Distance(ST_SetSRID( coords, 4326),ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography, True)
            ASC LIMIT :limit
            ";
        return self::cachedQuery(sprintf('nearby-city-%d',$this->id), $ttl, $sql, array_merge([
            'id'            => $this->id,
            'lat'           => $this->getLat(),
            'lng'           => $this->getLng(),
            'limit'         => $limit
        ], $filtersData));
    }




    /**
     * Copied from Place\City - @TODO
     *
     * @return array
     */
    public function toArray($columns = array()) : array {
        return parent::toArray($columns);
    }

    /**
     * returns the encoded form as it would be for a Search\Input\City object
     *
     * @return string
     */
    public function __toString() : string {
        return sprintf('c.%s', $this->id);
    }


    public function __get($key) {
        if (isset($this->{$key})) {
            return $this->{$key};
        }
        return new \Exception("Key not found: " . $key);
    }
}