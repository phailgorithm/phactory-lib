<?php namespace Core;

abstract class Geo extends \Model {


    protected static function getSource() : string { return ''; }

    public function __construct(array $data) {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }


    const TTL           = 60 * 60 * 24;
    const CACHE_PREFIX  = 'geo:5:'; // to be removed

    protected $customName, $customKey;
    protected static $db;

    protected $_cities;

    // public function __construct(array $data) {
    //     foreach ($data as $k => $v) {
    //         if (property_exists($this, $k)) {
    //             $this->$k = $v;
    //         }
    //     }
    // }

   /**
    * Geo cache prefix for all the Geo objects City\Region\Country
    *
    * @return string
    */
    protected function getCachePrefix() : string {
         return isset($_ENV['GEO_CACHE_PREFIX']) ? $_ENV['GEO_CACHE_PREFIX'] : 'geo:5:';
    }

   /**
    * Geo cache TTL for all the Geo objects City\Region\Country
    *
    * @return int
    */
    protected static function getCacheTtl() : int {
         return isset($_ENV['GEO_CACHE_TTL']) ? (int)$_ENV['GEO_CACHE_TTL'] : 60 * 60 * 24;
    }


    public function __get($k) {
        if (property_exists($this, $k)) {
            return $this->$k;
        }
        throw new \Exception("Property doesn't exist: $k");
    }

    /**
     * Just an alias
     *
     * @return static
     */
    public static function fromId(int $id) {
        return static::findOne($id);
    }

    /**s
     * Translation from url to ORM Objects
     *
     * @param string $key url input
     * @param string $localeCode localization code
     *
     * @return static object
     */
    public static function fromKey(string $key, string $localeCode) {
        $kindex = $key . '@' . $localeCode;
        return Cache::get(static::getCachePrefix().static::TABLE.':key:'.$kindex, static::getCacheTtl(), function() use ($kindex, $key, $localeCode) {
            $q = static::db()->query('SELECT * FROM ' . static::TABLE . ' WHERE kindex @> (ARRAY[?])', [ $kindex ]);
            $data = $q->fetch(\PDO::FETCH_ASSOC);

            if (!$data) {
                $localeCode = current(explode('_', $localeCode));
                $kindex = $key . '@' . $localeCode;
                $q = static::db()->query('SELECT * FROM ' . static::TABLE . ' WHERE kindex @> (ARRAY[?])', [ $kindex ]);
                $data = $q->fetch(\PDO::FETCH_ASSOC);

                if (!$data) {
                    throw new \Exception("Cannot find " . get_called_class() . ": $kindex", 404);
                }
            }
            return new static($data);
        });
    }

    /**
     * Returns the place key, optionally in a defined language
     *
     * @param string $locale
     *
     * @return string
     */
    public function getKey($locale = null) {
        if (is_null($locale)) {
            $locale = di()->getLocale();
        }

        if (isset($this->nk[$locale])) {
            return $this->nk[$locale]['key'];
        }

        $locale2 = current(explode('_', $locale));
        if (isset($this->nk[$locale2])) {
            return $this->nk[$locale2]['key'];
        }

        throw new \Exception("Geo key not found in locale: {$locale}/{$locale2}: " . get_called_class() . " ". $this->id);
    }


    /**
     * @return string
     */
    public function getName($locale = null) : string {
        if (is_null($locale)) { $locale = di()->getLocale(); }

        if (isset($this->nk[$locale])) {
            return $this->nk[$locale]['name'];
        }

        $locale2 = current(explode('_', $locale));
        if (isset($this->nk[$locale2])) {
            return $this->nk[$locale2]['name'];
        }

        throw new \Exception("Geo name not found in locale: {$locale}/{$locale2}:  " . get_called_class() . " ". $this->id);
    }

    /**
     * @return array
     */
    public function getKeys() : array {
        $output = [];
        foreach ($this->nk as $locale => $d) {
            $output[ $locale ] = $d['key'];
        }
        return $output;
    }

    /**
     * @return array
     */
    public function getNames() : array {
        $output = [];
        foreach ($this->nk as $locale => $d) {
            $output[ $locale ] = $d['name'];
        }
        return $output;
    }

    /**
     * @return string
     *
     * @throws \Exception no country timezone found
     */
    public function getTimezone() : string {
        if ($this instanceof Geo\Country) {
            $tld = $this->getCode();
        } else {
            $tld = $this->getCountry()->getCode();
        }

        if (!empty(app()->getConfig()->tldInfo->{$tld}->defaultTimezone)) {
            return app()->getConfig()->tldInfo->{$tld}->defaultTimezone;
        }

        # it's city or region                                 # it's country
        $countryId = !($this instanceof Geo\Country) ? $this->country_id : $this->id;

        # below fallback that can be used to retrieve the tz from `timezone` table
        $tzData = Cache::get(self::getCachePrefix().':tz:'.$countryId, 60 * 60 * 24 * 28, function() use ($countryId) {
            $q = static::db()->query(
                'SELECT id, code FROM timezone WHERE country_id = :country_id',
                [
                    'country_id' => $countryId
                ]
            );

            $tzData = $q->fetch(\PDO::FETCH_OBJ);

            return $tzData;
        });

        if (!is_null($tzData) && !empty($tzData->code)) {
            return trim($tzData->code);
        } else {
            throw new \Exception(get_called_class() . ' missing timezone ID:' . $this->id);
        }
    }

    /**
     * @return static
    public static function findOne(int $id) {
        return Cache::get(static::getCachePrefix().static::TABLE.':id:'.$id , static::getCacheTtl(), function() use ($id) {
            $q = static::db()->query('SELECT * FROM ' .static::TABLE. ' WHERE id  = ?', [ $id ]);
            $data = $q->fetch(\PDO::FETCH_ASSOC);

            if (!$data) {
                throw new \Exception("Cannot find " . get_called_class() . ": " . $id);
            }
            return new static($data);
        });
    }
     */


    /**
     * @return static
     */
    public static function findByKey(string $k) {
        return Cache::get(static::getCachePrefix().static::TABLE.':k:'.$k , static::getCacheTtl(), function() use ($k) {
            $q = static::db()->query('SELECT * FROM ' .static::TABLE. ' WHERE k  = ?', [ $k ]);
            $data = $q->fetch(\PDO::FETCH_ASSOC);

            if (!$data) {
                throw new \Exception("Cannot find " . get_called_class() . ": " . $k);
            }
            return new static($data);
        });
    }

    public function getViewName($locale = null) {
        return !!$this->customName ? $this->customName : \Localization::named(['names' => $this->getNames()], $locale);
    }

    public function getViewKey($locale = null) {
        return !!$this->customKey ? $this->customKey :  \Localization::keyed(['keys' => $this->getKeys()], $locale);
    }


    public function getLocalizedName() {
        $input = $this->toView();
        return \Localization::namedToLocalized(
            $input,
            \Localization::named($input));
    }

    public function getLat() {
        return $this->coords[0];
    }
    public function getLng() {
        return $this->coords[1];
    }

}