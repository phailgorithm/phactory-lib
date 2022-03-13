<?php namespace Db;

use Exception\NotFoundException;
use Illuminate\Support\Collection;

class Geo extends Model
{

    /**
     * @return Collection
     */
    public static function getRegioni() : Collection {
        return static::cachedQuery('regioni', 10,
            "SELECT * FROM geo WHERE type = 'r' ORDER BY name ASC"
        );
    }

    /**
     * @return Collection
     */
    public static function getProvince($regionId = null) : Collection {
        if (!$regionId) {
            return static::cachedQuery('province', 10,
                "SELECT * FROM geo WHERE type = 'p' ORDER BY name ASC"
            );
        } else {
            return static::cachedQuery('province', 10,
                "SELECT * FROM geo WHERE type = 'p' AND parent_region = $regionId ORDER BY name ASC"
            );
        }
    }

    /**
     * @return Collection
     */
    public static function getCities($provinciaId) : Collection {
        return static::cachedQuery('cities-' . $provinciaId, 10,
            "SELECT * FROM geo WHERE type = 'c' AND parent_province = ? ORDER BY name ASC",
            [ $provinciaId ]
        );
    }

    /**
     * @return static
     */
    public static function getProvinceFromSlug($provinceSlug) : self {
        $province = static::cachedQuery('province-slug-' . $provinceSlug, 10,
            "SELECT * FROM geo WHERE type = 'p' AND slug = ?",
            [ $provinceSlug ]
        );

        if (!$province->count()) {
            throw new \Core\Exception\NotFound;
        }

        return $province->first();
    }

    /**
     * @return static
     */
    public static function getCityImportFromSlug($citySlug) : self {
        $city = static::cachedQuery('city-slug-' . $citySlug, 10,
            "SELECT * FROM geo WHERE type = 'c' AND slug = ?",
            [ $citySlug ]
        );

        if (!$city->count()) {
            throw new \Core\Exception\NotFound;
        }

        return $city->first();
    }

    /**
     * @return static
     */
    public static function getCityFromSlug($citySlug, $provinciaSlug) : self {
        $city = static::cachedQuery('city-slug-' . $citySlug, 10,
            "SELECT * FROM geo WHERE type = 'c' AND slug = ? AND parent_province = (SELECT id FROM geo WHERE type = 'p' AND slug = ?)",
            [ $citySlug, $provinciaSlug ]
        );

        if (!$city->count()) {
            throw new \Core\Exception\NotFound;
        }

        return $city->first();
    }

    /**
     * @return static
     */
    public static function getCityFromId($cityId) : self {
        $city = static::cachedQuery('city-id-' . $cityId, 10,
            "SELECT * FROM geo WHERE id = ?",
            [ $cityId ]
        );

        if (!$city->count()) {
            throw new \Core\Exception\NotFound;
        }

        return $city->first();
    }

    /**
     * @return array
     */
    public function toArray($columns = null) : array {
        return array(
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'type'              => $this->type,
            'parent_region'     => $this->parent_region,
            'parent_province'   => $this->parent_province
        );
    }
}
