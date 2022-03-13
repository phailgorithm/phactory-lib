<?php namespace Phailgorithm\PhactoryLib;

use Illuminate\Support\Collection;

/**
 * The goal of this class is to provide a generic solution to geospatial queries
 */
abstract class Geospatial extends Model {

    /**
     * Defines the column name where the geometry data is stored
     */
    abstract protected static function getGeometryColumn() : string;


    /**
     * Generates query fragments to be used to build a geospatial query
     * Can be extended, IE adding more `WHERE` entries
     *
     * @return array
     */
    public static function generateQuery(array $columns) : array {
        $col = static::getGeometryColumn();
        $columns[] = sprintf('ST_Distance(%s::geography, ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography) AS distance', $col);
        $columns[] = sprintf('ST_AsGeoJSON(%s) AS %s', $col, $col);
        return array(
            'SELECT' => $columns,
            'FROM' => static::getSource(),
            'WHERE' => [
                sprintf('ST_DWithin(%s::geography,
                    ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography, :radius * 1609.34)', $col)
            ],
            'ORDER BY' => sprintf('ST_Distance(%s::geography,
                    ST_SetSRID(ST_MakePoint(:lng,:lat),4326)::geography) ASC', $col)
        );

    }


    /**
     * Most basic method to run a geospatial search, starting from a point and lookup within a radius
     *
     * @return Collection
     */
    public static function basicRadiusQuery(float $lat, float $lng, int $radius, array $columns = null) : Collection {
        $columns = is_null($columns) ? ['*'] : $columns;
        $query = static::generateQuery($columns);

        $query = sprintf('SELECT %s FROM %s WHERE %s ORDER BY %s',
            implode(', ', $query['SELECT']),
            $query['FROM'],
            implode(' AND ', $query['WHERE']),
            $query['ORDER BY']
        );

        $q = static::db()->query($query, [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius
        ]);

        $items = new Collection();
        while ($row = $q->fetch(\PDO::FETCH_ASSOC)) {
            $items[] = new static($row);
        }
        return $items;
    }

    /**
     * Assuming the `getGeometryColum()` is a `Point` data, extracts the inner coordinates
     *
     * @return ?array
     */
    public function getCoords() : ?array {
        $col = static::getGeometryColumn();
        if (!!$this->{$col}) {
            $col = json_decode($this->{$col});
            return [
                'lat' => $col->coordinates[1],
                'lng' => $col->coordinates[0]
            ];
        }
    }



}
