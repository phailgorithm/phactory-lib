<?php namespace Geo;

/*

CREATE MATERIALIZED VIEW public.geo_region AS
 SELECT a.id,
    a.country_id,
    a.active,
    json_object_agg(b.locale_code, json_build_object('name', b.name, 'key', b.key)) AS nk,
    array_agg(((b.key || '@'::text) || (c.locale_code)::text)) AS kindex,
    MAX(b.key) as k
   FROM ((
     public.region a
     JOIN ( SELECT
                c1.id,
                c1.region_id,
                c1.language_id,
                c1.name,
                c1.key,
                c2.locale_code
            FROM (
                public.region_name c1
                JOIN public.language c2 ON ((c1.language_id = c2.id))
            )
     ) b ON ((a.id = b.region_id)))
     JOIN public.language c ON ((b.language_id = c.id)))
  WHERE (a.active = true)
  GROUP BY a.id
  WITH NO DATA;

ALTER TABLE public.geo_region OWNER TO virail;
CREATE UNIQUE INDEX geo_region_src_id_idx ON public.geo_region USING btree (id);
CREATE INDEX geo_region_src_kindex_idx ON public.geo_region USING gin (kindex);

 */

use Geo;
use Illuminate\Contracts\Support\Arrayable;


class Region extends Geo implements Arrayable {

    const TABLE = 'geo_region';

    protected $id, $country_id, $active, $nk;

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

    public function getTld() {
        switch ($this->code_iso_3166) {
            case 'gb':
                return 'uk';
            default:
                return $this->code_iso_3166;
        }
    }

    /**
     * @return Country object
     */
    public function getCountry() : Country {
        if (!isset($this->_country)) {
            $this->_country = Country::findOne($this->country_id);
        }
        return $this->_country;
    }


    public function getCountryCode() {
        return $this->getCountry()->getCountryCode();
    }



    /**
     * @param integer limit of cities to select
     *
     * @return array of City objects
     */
    public function getCities($limit = 10) : array {
        if (empty($this->_cities)) {
            $this->_cities = City::fromRegionID($this->id, $limit);
        }
        return $this->_cities;
    }

    /**
     * @return bool
     */
    public function isActive() : bool {
        return $this->active;
    }

    /**
     * Copied from Place\Region - @TODO
     *
     * @return array
     */
    public function toArray() : array {
        return [
            'id' => $this->id,
            'names' => $this->getNames(),
            'keys' => $this->getKeys(),
            'country' => $this->getCountry()->toArray()
        ];
    }

    public function toView() : array {
        return [
            'id'        => $this->id,
            'country'   => $this->getCountry()->getLocalizedName(),
            'name'      => $this->getViewName(),
            'key'       => $this->getViewKey(),
            'enCountry' => \Localization::named(['names' => $this->getCountry()->getNames()], 'en'),
            'enName'    => $this->getViewName('en'),
            'enkey'     => $this->getViewKey('en'),
            'active'    => $this->isActive(),
            'named'     => $this->getViewName(),
            'keyed'     => $this->getViewKey()
        ];
    }

}