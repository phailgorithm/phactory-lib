<?php namespace Db;

use Illuminate\Support\Collection;

use Phalcon\Mvc\Model\Resultset\Simple as Resultset;
use Phalcon\Mvc\Model\MetaData;

class City extends Model {

    static $lastTotal = 0;


    public $id;

    public $name;

    public $slug;

    public $population;

    public $coords;

    public $origin_id;


    public function metaData()
    {
        return array(

            //Every column in the mapped table
            MetaData::MODELS_ATTRIBUTES => array(
                'id', 'name', 'slug', 'coords'
            ),

            //Every column part of the primary key
            MetaData::MODELS_PRIMARY_KEY => array(
                'id'
            ),

        );
    }

    public function columnMap() {
        return array(
            'id'         => 'id',
            'name'       => 'name',
            'slug'       => 'slug',
            'population' => 'population',
            'origin_id'  => 'origin_id',
            'coords'     => 'coords'
        );
    }


    /**
     * @return array
     */
    public function toArray($columns = null) : array {
        return array(
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'population' => $this->population,
            'coords'     => is_array($this->coords) ? $this->coords : explode(',', trim($this->coords,'()')),
            'url'        => '/'. $this->slug
        );
    }

    /**
     * Shortcut to lookup for objects in db by key and throw exception if not found
     *
     * @return static
     */
    public static function getInstance(string $value) : self {
        $model = static::cachedQuery('city:' . $value, -1,
            'SELECT * FROM geo_city WHERE slug = ?',
            [ $value ]
        );
        if (!$model->count()) {
            throw new \Core\Exception\NotFound;
        }
        return $model->first();
    }


    /**
     * Matching cities to string
     *
     * @return static
     */
    public static function getMatches(string $value) : Collection {
        $values = explode(' ', urldecode($value));
        foreach ($values as &$v) {
            $v .= ':*';
        }
        return static::cachedQuery('city-axcx:' . $value, -1,
            'SELECT * FROM geo_city WHERE to_tsvector(name) @@ to_tsquery(?) ORDER BY population DESC LIMIT 10',
            [ implode(' | ', $values) ]
        );
    }


    /**
     * @return Collection
     */
    public static function getTop(int $limit = 25) : Collection {
        return static::cachedQuery('top-'.$limit, 10,
            'SELECT * FROM ( SELECT * FROM geo_city ORDER BY population DESC LIMIT :limit ) as top_cities ORDER BY 1 ASC',
            [ 'limit' => $limit ]
        );
    }


    /**
     * List of categories available in this city.
     *
     * @return Collection
     */
    public function getCategories() : Collection {
        $return = new Collection;
        $data = static::cachedRawQuery('city:cat:' . $this->id, -1,
            'SELECT A.* FROM category AS A INNER JOIN city_categories AS B ON (A.id = B.category_id) WHERE B.city_id = ? AND A.status = \'published\'',
            [ $this->id ]
        );
        foreach ($data as $cat) {
            $return[] = new Category($cat);
        }
        return $return;
        // d($data);

        // return \Core\Cache::get('city:categories:'.$this->id, -1, function() {
        //     foreach (Category::getAll() as $category) {
        //         $rows = $this->getCategoryAggregated($category);
        //         if ($rows->count() > 0) {
        //             $return[] = new Collection(array(
        //                 'category'  => $category->name,
        //                 'tags'      => $rows->sortByDesc('count'),
        //                 'slug'      => $category->slug,
        //                 'count'     => $rows->reduce(function(int $i, array $e) {
        //                     return $i + $e['count'];
        //                 }, 0)
        //             ));
        //         }
        //     }
        //     return $return;
        // });
    }

    /**
     *
     */
    public function getCategoryAggregated(Category $category) : Collection {
        $sql = "
            SELECT STRING_AGG(DISTINCT id::text ,'|') AS ids,
                   -- STRING_AGG(DISTINCT tag::text, '|') AS tags,
                   COUNT(DISTINCT id)
            FROM (
                SELECT id
                    -- , UNNEST(tags) AS tag
                FROM company_denormalized
                WHERE :category_id = ANY(categories)
                AND   related_city = :city
            ) X;
            ";
                // -- postgis.ST_DWithin(D.geom::postgis.geography, postgis.ST_SetSRID( postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography, :nearby_limit * 1609.34)

        return self::cachedRawQuery(sprintf('city-category-agg:%d:%d',$this->id,$category->id), -1, $sql, [
            // 'lat'           => $this->getLat(),
            // 'lng'           => $this->getLng(),
            'city'          => $this->id,
            'category_id'   => $category->id,
            // 'nearby_limit'  => 30.0
        ]);
    }


    /**
     *
     */
    public function getNearbyCities() : Collection {
        $sql = "
            SELECT *,
                    degrees(postgis.ST_Azimuth( geom::postgis.geography,postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326) )) AS azimuth,
                    postgis.ST_Distance(geom::postgis.geography,postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography, True) AS distance
            FROM geo_city
            WHERE id != :id
            ORDER BY postgis.ST_Distance(geom::postgis.geography,postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography, True)
            ASC LIMIT 60
            ";
        return self::cachedQuery(sprintf('nearby-city-%d',$this->id), -1, $sql, [
            'id'            => $this->id,
            'lat'           => $this->getLat(),
            'lng'           => $this->getLng(),
        ]);
    }



    /**
     * Returns all company related to a city via table column ordered by proximity
     *
     *
     * @param Category
     * @param int
     * @param int
     *
     * @return Collection
     */
    public function getCompaniesByCategory(Category $category, int $currentPage = 1, bool $strict = false) : Collection {
        // throw new \Exception("Error Processing Request", 1);
        $sql = "
        SELECT * FROM (
            SELECT 'related' AS type, *,
                   postgis.ST_Distance(geom::postgis.geography, postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography) AS dist
            FROM company_denormalized
            WHERE related_city = :city
            AND   :category_id = ANY(categories)
            -- AND   details::text NOT IN ('[]', '{}', :nearby_limit)
            ";

        if (!$strict) {
            $sql .= "
            UNION ALL

            SELECT 'proximity' AS type, *,
                   postgis.ST_Distance(geom::postgis.geography, postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography) AS dist
            FROM company_denormalized
            WHERE :category_id = ANY(categories)
            AND related_city != :city
            AND related_city IS NOT NULL
            AND postgis.ST_DWithin(geom::postgis.geography,postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography, :nearby_limit * 1609.34)

            -- AND   details::text NOT IN ('[]', '{}')
            ";
        }

        $sql .= " ) A ORDER BY dist ASC";
        $params = [
            'lat'           => $this->getLat(),
            'lng'           => $this->getLng(),
            'city'          => $this->id,
            'category_id'   => $category->id,
        ];
        if (!$strict) {
            $params['nearby_limit'] = 30;
        }


        $list = self::cachedRawQuery(sprintf('city-category:%d:%d:%d',$strict,$this->id,$category->id), -1, $sql, $params);

        static::$lastTotal = $list->count();

        $pageSize = \ListingController::LIMIT_PER_PAGE;

        return $list
            ->slice(($currentPage-1) * $pageSize, $pageSize)
            ->transform(function($e) {
                return Company::createModelFromArray($e, $getTokens = false);
            });
    }







    ################################################################################################################################################





    public static function findClosest($lat, $lng) {
        return self::findNear($lat, $lng, -1, $limit = 1, $radius = 5, 1)->first();
        // ->sort(function ($a, $b) use ($lat, $lng) {

        //     // dd($a, $a->distanceTo($lat,$lng) , $b->distanceTo($lat,$lng));
        //     return $a->distanceTo($lat,$lng) > $b->distanceTo($lat,$lng);
        // })->first();
    }

    public static function findNearCoords($lat, $lng, $limit = 4) {
        return self::findNear($lat, $lng, -1, $limit, 30, 0);
        // ->sort(function ($a, $b) use ($lat, $lng) {

        //     // dd($a, $a->distanceTo($lat,$lng) , $b->distanceTo($lat,$lng));
        //     return $a->distanceTo($lat,$lng) > $b->distanceTo($lat,$lng);
        // })->first();
    }

    public static function getBiggest($limit = 10) {
        return self::query()->orderBy('population DESC')->limit($limit)->execute();
    }

    public function getNear($limit = 4, $nearby_limit = 50) {
        return self::findNear($this->getLat(), $this->getLng(), $this->id, $limit, $nearby_limit);
    }


    public static function findNear( $lat, $lng, $notId = -1, $limit, $nearby_limit, $sortBy = 0) {
        $db = (new self)->getReadConnection();

        $sql  = "SELECT * FROM geo_city WHERE id != :id AND postgis.ST_DWithin(geom::postgis.geography,postgis.ST_SetSRID(postgis.ST_MakePoint(:lat,:lng),4326)::postgis.geography, :nearby_limit * 1609.34) ORDER BY " . (($sortBy == 0) ? 'population DESC, name ASC' : self::distanceValue() . ' ASC')  . " LIMIT :limit";

        $params = array(
            'id' => $notId,
            'lat' => $lat,
            'lng' => $lng,
            'limit' => 1+$limit,
            'nearby_limit' => $nearby_limit
        );

        $model = new self;
        $set = new Resultset(
            null,
            $model,
            $model->getReadConnection()->query($sql, $params)
        );

        return new Collection($set->filter(function($e) { return $e; }));
    }

    public function getClosestBig() {
        $db = (new self)->getReadConnection();

        $sql  = "SELECT * FROM geo_city WHERE id != :id ORDER BY population DESC, " . self::distanceValue() . " DESC LIMIT 1";

        $params = array(
            'id' => $this->id,
            'lat' => $this->getLat(),
            'lng' => $this->getLng()
        );

        $resultSet = $db->query($sql, $params);

        return new self($resultSet->fetch());
    }




    public function getLat() {
        return (is_array($this->coords)) ? $this->coords[0] : explode(',', trim($this->coords,'()')) [0];
    }

    public function getLng() {
        return (is_array($this->coords)) ? $this->coords[1] : explode(',', trim($this->coords,'()')) [1];
    }

    public function distanceTo($lat, $lng) {
        $theta = $this->getLng() - $lon2;
        $dist = sin(deg2rad($this->getLat())) * sin(deg2rad($lat)) +  cos(deg2rad($this->getLat())) * cos(deg2rad($lat)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return ($miles * 1.609344);
    }

    public function toForm() {
        return [
            'val' => $this->slug,
            'label' => $this->name
        ];
    }

}