<?php namespace Core;

use Requests;
use Localcache;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Still very raw, all data encoded
 */

class CMS extends Collection implements Arrayable {

    public function __construct(array $filters = array()) {
        $this->filters    = $filters;
        $this->items = new Collection();
    }


    /**
     * Generic TTL for CMS-related objects. Overridable from extended classes
     *
     * @return int
     */
    protected function getCacheTtl() : int {
        return 60*60*24*30*6;
    }

    /**
     * Generic Cache prefix for CMS-related objects. Overridable from extended classes
     *
     * @return string
     */
    protected function getCachePrefix() : string {
        return (isset($_ENV['CMS_CACHE_PREFIX']) ? $_ENV['CMS_CACHE_PREFIX'] : '') .  VERSION . ':';
    }



    public function fetch() : Collection {
        $cacheKey = $this->getCachePrefix() . get_called_class() . ':' . json_encode($this->filters);
        $q = $this->query();
        while ($item = $q->fetch(\PDO::FETCH_ASSOC)) {
            $this->items[] = new Collection($item);
        }

        return $this;
    }

    public function toArray() : array {
        return $this->items;
    }

    public function firstOrFail() : Collection {

        if ($this->items->count() == 0) {
            throw new \Exception('Empty element list');
        }
        return $this->items->first();
    }

    protected function getSqlQuery() : string {
        throw new \Exception("Query must be implemented in subclasses");
    }

    protected function query() {
        return di()->getDb()->query($this->getSqlQuery(), $this->filters);
    }

}
