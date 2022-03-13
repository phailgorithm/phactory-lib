<?php namespace Core\Autocomplete\Backends;

use Redis as RedisConnection;
use Core\Autocomplete\Backend;
use Core\Autocomplete\Match;
use Illuminate\Support\Collection;

class Redis extends Backend {

    public function run() : Collection {
        $matches = new Collection;
        try {
            $config = parse_url($this->options['uri']);
            $redis = new RedisConnection;
            $redis->connect($config['host'], $config['port']);
            isset($config['scheme']) && $redis->setOption(RedisConnection::OPT_PREFIX, sprintf(':%s:',$config['scheme']));
            isset($config['path'])   && $redis->select(ltrim($config['path'], '/'));

            $term   = $this->getTerm();
            $locale = $this->getLocale();
            $set    = $this->options['targetKeyPrefix'] . $locale;

            foreach ($redis->zrangebylex($set,"[$term","[$term\xff", 0, 200) as $entry) {
                // $this->matches[] = $this->transformEntry($entry);
            }

            $matches[] = new Match('id1', ['set' => $set], $this);

        } catch (\Exception $e) {

        }
        return $matches;
    }
}