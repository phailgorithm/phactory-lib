<?php namespace Phailgorithm\PhactoryLib\Model;

use Phailgorithm\PhactoryLib\Model;
use Localcache;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Twig_SimpleFilter;


/**
 * Random text generator class
 */
class Spinner extends Model implements Arrayable {

    protected static function getSource() : string { return 'spinner_item'; }

    protected $id, $merge, $token_count, $input, $locale, $condition, $seed, $ttl;

    public function __construct(string $id, array $input = array(), int $seed = null, int $ttl = 0, array $spinner = null) {
        $this->id = explode(':',$id,2);
        $this->locale = is_null($locale) ? di()->getLocale() : $locale;
        $this->seed = is_null($seed) ? crc32($this->id[1]) : intval($seed);
        $this->input = $input;
        $this->items = new Collection();
        $this->ttl = $ttl;
        if (is_null($spinner)) {
            $this->fetchItems();
        } else {
            foreach (['merge', 'token_count', 'condition', 'items'] as $k) {
                $this->$k = $spinner[$k];
            }
        }


        return $this;
     }

    private function fetchItems() {
        // $cacheKey = $this->getCachePrefix() . get_called_class() . ':' . json_encode($this->filters);
        $q = "SELECT A.id, A.merge, A.token_count, A.condition, json_agg(json_build_object('id', B.id, 'content_type', B.content_type, 'content', B.content)) as data
            FROM spinner_item AS A
            INNER JOIN spinner_variation AS B ON A.id = B.spinner_item
            WHERE A.". $this->id[0] ." = ?
            GROUP BY A.id, A.merge, A.token_count, A.condition
        ";

        $item = static::cachedRawQuery('spinner:'.implode(':',$this->id), $this->ttl ,$q, [ $this->id[1] ])->first();

        if (!$item) {
            throw new \Phailgorithm\PhactoryLib\Exception\NotFound("Item not found: " . $this->id );
        }

        foreach (['merge', 'token_count', 'condition'] as $k) {
            $this->$k = $item[$k];
        }
        $this->items = json_decode($item['data'], true);
   }


    public function toString() : string {

        srand($this->seed);
        if ($this->merge) {

            $output = array();
            for ($i = $this->token_count; $i > 0; $i--) {
                $y = rand(0, count($this->items)-1);
                $output[] = $this->items[$y];
                array_splice($this->items, $y, 1);
            }


        } else {
            $output = [ $this->items[ rand(0,count($this->items)-1) ] ];
        }

        $preparsed = [];
        foreach ($output as $e) {

            $content_type = $e['content_type'];
            if (
                #This condition might be obsolete after adding content_type
                /* substr_count( $variation['content'], "\n" ) > 0 || */
                $content_type == 'markdown'
            ) {
                $Parsedown = new \Parsedown();
                $variation = htmlspecialchars_decode($Parsedown->text($e['content'] ));
// d($variation);
                // $variation = Markdown::defaultTransform($e['content']);
            } else {
                $variation = $e['content'];
            }

            $preparsed[] = $variation ;
        }
        $preparsed = sprintf(
            "{%% if (%s) %%}%s{%% endif %%}",
            $this->condition,
            implode(' ', $preparsed)
        );

        try {
            $tplName = uniqid( 'string_template_', true );
            $parsed = di()->getTwig()->renderTemplate($preparsed , $this->input);

        } catch (\Exception $e) {
            throw $e;
        }
        srand(microtime(true));

        return $parsed;
    }


    public function toArray($columns = null) : array {
        // $this->process();
        return [
            'id' => $this->id,
            'input' => $this->input,
            'items' => $this->items
        ];
    }
}
