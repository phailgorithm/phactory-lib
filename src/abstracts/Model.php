<?php namespace Phailgorithm\PhactoryLib;

use Model\Cache;
use Illuminate\Support\Collection;
use \Illuminate\Contracts\Support\Arrayable;

abstract class Model implements Arrayable {

    /**
     * Defines the table name. to be implemented in child classes
     *
     * @return string
     */
    abstract protected static function getSource() : string;


    public function __construct(array $data = array()) {
        foreach ($data as $k => $v) {
            $this->$k = $v;
        }
    }


    /**
     * Generic Cache prefix, useful to version specific models without invalidating every cache entry
     *
     * @return string
     */
    protected static function getCacheKeyPrefix() : string {
        return sprintf('v.%s:p.%s:d.%s:t.%s',
            $_ENV['CACHE_VERSION'] ?? '',
            di()->getProject(),
            di()->getDomain(),
            static::getSource()
        );
    }

    /**
     * Default cache lifetime
     *
     * @return int
     */
    protected static function getCacheTtl() : int {
        return di()->getCache()->getFrontend()->getLifetime() ?: 0;
    }

    /**
     * Shortucut to avoid using `di`
     */
    protected static function db() : \Phalcon\Db\Adapter\Pdo {
        return di()->getDb();
    }


    /**
     * Find one row by id, optionally by another column name
     *
     * @param $id
     * @param $colum
     *
     * @return static
     */
    public static function findOne($id, string $column = 'id', string $fields = '*', string $cacheKey = null, int $cacheTtl = null) : ?self {
        $cacheKey = is_null($cacheKey) ? sprintf('%s:c.%s:v.%s', static::getCacheKeyPrefix(), $column, $id) : $cacheKey;
        $cacheTtl = is_null($cacheTtl) ? static::getCacheTtl() : $cacheTtl;

        return Cache::get($cacheKey, $cacheTtl, function() use ($column, $fields, $id) {
            $q = static::db()->query('SELECT '. $fields.' FROM ' .static::getSource(). ' WHERE ' . $column . '  = ?', [ $id ]);
            $data = $q->fetch(\PDO::FETCH_ASSOC);
            if (!$data) {
                throw new \Core\Exception\NotFound("Cannot find " . get_called_class() . ": " . $id);
            }
            return new static($data);
        });
    }


    /**
     * @return Collection
     */
    private static function _cachedQuery(string $cacheKey = null, int $ttl = null, string $query, array $parameters = array(), bool $returnModel = true) : Collection {
        $cacheKey = sprintf('%s:k.%s',
                static::getCacheKeyPrefix(),
                $cacheKey ?: md5(serialize([$query,$parameters]))
        );

        return Cache::get(
            $cacheKey,
            $ttl ?? static::getCacheTtl(),
            function() use ($query, $parameters, $returnModel) {
                return static::query ($query, $parameters, $returnModel);
            }
        );
    }




    /**
     * @return Collection
     */
    public static function select(array $where = array(), string $fields = '*', bool $returnModel = true) : Collection {
        $items = new Collection;
        $strWhere = array();
        foreach ($where as $k => $v) {
            $strWhere[] = sprintf('%s = :%s', $k, $k);
        }

        $q = static::db()->query(
            sprintf('SELECT %s FROM %s WHERE %s', $fields, static::getSource(), implode(' AND ', $strWhere)),
            $where
        );
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            $items[] = $returnModel ? new static($row) : $row;
        }
        return $items;
    }


    /**
     * @return Collection
     */
    public static function query(string $query, array $parameters = array(), bool $returnModel = true) : Collection {
        $items = new Collection;
        $q = static::db()->query($query, $parameters);
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            $items[] = $returnModel ? new static($row) : $row;
        }
        return $items;
    }


    /**
     * @return Collection
     */
    public static function cachedQuery(string $cacheKey = null, int $ttl, string $query, array $parameters = array()) : Collection {
        return self::_cachedQuery($cacheKey, $ttl, $query, $parameters, $returnModel = true);
    }

    /**
     * @return Collection
     */
    public static function cachedRawQuery(string $cacheKey = null, int $ttl = 0, string $query, array $parameters = array()) : Collection {
        return self::_cachedQuery($cacheKey, $ttl, $query, $parameters, $returnModel = false);
    }


    /**
     * All-purpose toArray() function
     *
     * @return array
     */

    public function toArray($columns = array()) : array {
        // d($this);
        $reflection = new ReflectionClass($this);
        $vars = $reflection->getProperties(); //ReflectionProperty::IS_PROTECTED);
        $return = array();
        foreach ($vars as $v) {
            $val = $this->{$v->getName()};
            if ($val instanceOf Arrayable ) {
                $val = $val->toArray($columns);
            }
            $return[ $v->getName() ] = $val;
        }
        return $return;
    }

}



























// <?php namespace Db;

// use Core\Cache;
// use Illuminate\Support\Collection;

// class Model extends \Phalcon\Mvc\Model implements \Illuminate\Contracts\Support\Arrayable {

//     // protected static function distanceFilter() {
//     //     return self::distanceValue() . " < :nearby_limit";
//     // }
//     // protected static function distanceValue() {
//     //     return "ROUND( (2 * 3961 * asin(sqrt((sin(radians((:lat - coords[0]) / 2))) ^ 2 + cos(radians(coords[0])) * cos(radians(:lat)) * (sin(radians((:lng - coords[1]) / 2))) ^ 2) ) )::numeric ,2)";
//     // }


//     // protected static function distanceIndexFilter() {
//     //     return "ST_DWithin(geom::geography,ST_SetSRID(ST_MakePoint(:lat,:lng),4326)::geography, :nearby_limit * 1609.34)";
//     // }

//     /**
//      * @return Collection
//      */
//     private static function _cachedQuery(string $cacheKey = null, int $ttl = 0, string $query, array $parameters = array(), bool $returnModel = true) : Collection {
//         if (is_null($cacheKey)) {
//             $cacheKey = md5(serialize([$query,$parameters]));
//         }
//         return \Core\Cache::get($cacheKey, $ttl, function() use ($query, $parameters, $returnModel) {
//             $collection = new Collection;
//             $q = (new static)->getReadConnection()->query($query, $parameters);
//             while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
//                 $collection[] = $returnModel ? new static($row) : $row;
//             }
//             return $collection;
//         });
//     }


//     /**
//      * @return Collection
//      */
//     public static function cachedQuery(string $cacheKey = null, int $ttl = 0, string $query, array $parameters = array()) : Collection {
//         return self::_cachedQuery($cacheKey, $ttl, $query, $parameters, $returnModel = true);
//     }

//     /**
//      * @return Collection
//      */
//     public static function cachedRawQuery(string $cacheKey = null, int $ttl = 0, string $query, array $parameters = array()) : Collection {
//         return self::_cachedQuery($cacheKey, $ttl, $query, $parameters, $returnModel = false);
//     }


// }