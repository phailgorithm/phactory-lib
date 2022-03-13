<?php namespace Core\CMS;

use Illuminate\Support\Collection;

class SpinnerItemVariation extends \Core\CMS {

    protected function getSqlQuery() : string {
        return
            "SELECT item.sort, item.name, item.condition, variation
             FROM spinner_item AS item INNER JOIN spinner_item_variation
             ON (item.id = spinner_item_variation.item)
             WHERE locale = :locale AND transport = :transport ORDER BY item.id";
    }

    public static function get(array $filters) : Collection {
        $cms = new self('spinner_item_variation', ['item.sort', 'item.name', 'item.condition', 'variation'], $filters);
        $cms->fetch();
        return $cms->data;
    }

    protected function fetchFromDb() : Collection {
        $data = parent::fetchFromDb();

        $return = new Collection;
        foreach ($data as $i) {
            if (!isset($return[ $i['name'] ] )) {
                $return[ $i['name'] ] = new Collection([
                    'sort' => $i['sort'],
                    'condition' => $i['condition'],
                    'variations' => new Collection
                ]);
            }
            $return[ $i['name'] ]['variations'][] = $i['variation'];
        }
        return $return->sortBy('sort', SORT_NUMERIC)->filter(function($e) {
            return is_numeric($e['sort']);
        });
    }

    protected function fetchFromApi() : Collection {
        $data = parent::fetchFromApi();
        $return = new Collection;
        foreach ($data as $i) {
            if (!isset($return[ $i['item']['name'] ] )) {
                $return[ $i['item']['name'] ] = new Collection([
                    'sort' => $i['item']['sort'],
                    'condition' => $i['item']['condition'],
                    'variations' => new Collection
                ]);
            }
            $return[ $i['item']['name'] ]['variations'][] = $i['variation'];
        }

        return $return->sortBy('sort', SORT_NUMERIC)->filter(function($e) {
            return is_numeric($e['sort']);
        });
    }
}