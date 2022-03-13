<?php namespace Core;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

use Model;

class Comment extends Collection implements Arrayable {

        protected function array_totree( &$a, $parent_key, $children_key='children' )
        {
            $orphans = true; $i;
            while( $orphans )
            {
                $orphans = false;
                foreach( $a as $k=>$v )
                {
                    // is there $a[$k] sons?
                    $sons = false;
                    foreach( $a as $x=>$y )
                    {
                        if( $y[$parent_key]!=false and $y[$parent_key]==$k )
                        {
                            $sons=true; $orphans=true; break;
                        }
                    }

                    // $a[$k] is a son, without children, so i can move it
                    if( !$sons and $v[$parent_key]!=false )
                    {
                        $parent = $v[$parent_key];
                        unset( $v[$parent_key] );
                        $a[$parent][$children_key][$k] = $v;
                        unset( $a[$k] );
                    }
                }
            }
        }

    public function __construct() {


$data = array(
    '1' => array( 'author' => 'xxx', 'comment' => "A", ),
    '2' => array( 'author' => 'xxx', 'comment' => "B", ),
    '3' => array( 'author' => 'xxx', 'comment' => "C", ),
    '4' => array( 'author' => 'xxx', 'comment' => "D", ),
    '5' => array( 'author' => 'xxx', 'comment' => "one", 'parent' => '1' ),
    '6' => array( 'author' => 'xxx', 'comment' => "two", 'parent' => '1' ),
    '7' => array( 'author' => 'xxx', 'comment' => "three", 'parent' => '1' ),
    '8' => array( 'author' => 'xxx', 'comment' => "node 1", 'parent' => '2' ),
    '9' => array( 'author' => 'xxx', 'comment' => "node 2", 'parent' => '2' ),
    '10' => array( 'author' => 'xxx', 'comment' => "node 3", 'parent' => '2' ),
    '11' => array( 'author' => 'xxx', 'comment' => "I", 'parent' => '9' ),
    '12' => array( 'author' => 'xxx', 'comment' => "II", 'parent' => '9' ),
    '13' => array( 'author' => 'xxx', 'comment' => "III", 'parent' => '9' ),
    '14' => array( 'author' => 'xxx', 'comment' => "IV", 'parent' => '9' ),
    '15' => array( 'author' => 'xxx', 'comment' => "V", 'parent' => '9' ),
);

$this->array_totree($data, 'parent');
echo json_encode($data);
    d($data);


        $q = "SELECT * FROM comment WHERE rel = 'xxx'";

        $item = Model::query($q);

        d($item);

        if (!$item) {
            throw new Core\Exception\NotFound("Item not found: " . $this->id );
        }



    }

    private function fetchItems() {
        // $cacheKey = $this->getCachePrefix() . get_called_class() . ':' . json_encode($this->filters);
        $q = "SELECT A.id, A.merge, A.token_count, A.condition, json_agg(json_build_object('id', B.id, 'content_type', B.content_type, 'content', B.content)) as data
            FROM spinner_item AS A
            INNER JOIN spinner_variation AS B ON A.id = B.spinner_item
            WHERE A.". $this->id[0] ." = ?
            GROUP BY A.id, A.merge, A.token_count, A.condition
        ";

        $item = Model::cachedRawQuery('spinner:'.implode(':',$this->id), $this->ttl ,$q, [ $this->id[1] ])->first();

        if (!$item) {
            throw new Core\Exception\NotFound("Item not found: " . $this->id );
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


    public function toArray() : array {
        // $this->process();
        return [
            'id' => $this->id,
            'input' => $this->input,
            'items' => $this->items
        ];
    }
}
