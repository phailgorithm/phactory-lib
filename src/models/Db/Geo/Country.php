<?php namespace Geo;

/*
CREATE MATERIALIZED VIEW public.geo_country AS
 SELECT a.id,
    a.code_iso_3166 AS code,
    json_object_agg(b.locale_code, json_build_object('name', b.name, 'key', b.key)) AS nk,
    array_agg(((b.key || '@'::text) || (c.locale_code)::text)) AS kindex,
    MAX(b.key) as k
   FROM ((
     public.country a
     JOIN ( SELECT c1.id,
            c1.language_id,
            c1.country_id,
            c1.name,
            c1.key,
            c2.locale_code
           FROM (
                public.country_name c1
                JOIN public.language c2 ON ((c1.language_id = c2.id)))) b ON ((a.id = b.country_id))
           )
     JOIN public.language c ON ((b.language_id = c.id)))
  GROUP BY a.id
  WITH NO DATA;

ALTER TABLE public.geo_country OWNER TO virail;
CREATE UNIQUE INDEX geo_country_src_id_idx ON public.geo_country USING btree (id);
CREATE INDEX geo_country_src_kindex_idx ON public.geo_country USING gin (kindex);
*/

use Geo;
use Illuminate\Contracts\Support\Arrayable;

class Country extends Geo implements Arrayable {

    const TABLE = 'geo_country';
    protected $id, $code, $nk, $population;

    public function __construct(array $data) {
        parent::__construct($data);
        if (is_string($this->nk)) {
            $this->nk = json_decode($this->nk, true);
        }
    }

    /**
     * @return int
     */
    public function getId() : int {
        return $this->id;
    }

    /**
     * @param integer limit of cities to select
     *
     * @return array of City objects
     */
    public function getCities($limit = 10) : array {
        if (empty($this->_cities)) {
            $countryCitiesFile = app()->getConfig()->application->cacheDir . "country-cities.{$this->getCode()}.php";
            if (
                file_exists($countryCitiesFile)
                && ( filemtime($countryCitiesFile) + app()->getConfig()->application->countryCitiesFileLifetime  > time())
            ) {
                $this->_cities = unserialize(file_get_contents($countryCitiesFile));
            } else {
                $this->_cities = City::fromCountryID($this->id, $limit);
                $fileContent = serialize($this->_cities);
                file_put_contents($countryCitiesFile, $fileContent);
            }

        }
        return $this->_cities;
    }

    public function getTld() {
        switch ($this->code) {
            case 'gb':
                return 'uk';
            default:
                return $this->code;
        }
    }

    public function getCode() {
        return $this->getTld();
    }

    public function getCountryCode() {
        return $this->getCode();
    }

    /**
     * @return static
     */
    public static function findByCode(string $code) {
        return \Localcache::get(static::getCachePrefix().static::TABLE.':k:'.$code , static::getCacheTtl(), function() use ($code) {
            $q = static::db()->query('SELECT * FROM ' .static::TABLE. ' WHERE code  = ?', [ $code ]);
            $data = $q->fetch(\PDO::FETCH_ASSOC);

            if (!$data) {
                throw new \Exception("Cannot find " . get_called_class() . ": " . $code);
            }
            return new static($data);
        });
    }

    /**
     *
     * @return array
     */
    public function toArray() : array {
        return [
            'id' => $this->id,
            'code' => $this->getCode(),
            'names' => $this->getNames(),
            'keys' => $this->getKeys(),
        ];
    }

    public function toView() {
        return [
            'id'        => $this->id,
            'name'      => $this->getViewName(),
            'key'       => $this->getViewKey(),
            'enName'    => $this->getViewName('en'),
            'enkey'     => $this->getViewKey('en'),
            'named'     => $this->getViewName(),
            'keyed'     => $this->getViewKey(),
            'code'      => $this->getCode()
        ];
    }

}