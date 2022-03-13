<?php namespace Core\Autocomplete\Backends;

use Core\Autocomplete;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Collection;

class EsLang extends Autocomplete {
    const INDEX = 'ac_lang';

    private $esClient;

    public function __construct($input, $locale = null, Collection $matches = null) {
        parent::__construct($input, $locale, $matches);

        $esParams = [
            'client' => [
                'timeout' => 2.5,
                'connect_timeout' => 2.5
            ]
        ];

        $this->esClient = ClientBuilder::create()
            ->setHosts(explode(',', app()->getConfig()->application->elasticsearchAutocomplete))
            ->setConnectionParams($esParams)
            ->build();
    }

    protected function getCompletionRequest() : array {
        return [
            'prefix' => $this->getTerm(),
            'completion' => [
                'field' => 'suggest',
                'fuzzy' => [
                    'fuzziness' => 2
                ],
                'size' => 30,
                'contexts' => [
                    'locale' => [ app()->getLocale() ]
                ]
            ]
        ];
    }

    public function match() : bool {
        try {
            $params = [
                'index' => static::INDEX,
                'body'  => [
                    'suggest' => [
                        'ac' => $this->getCompletionRequest()
                    ]
                ]
            ];

            $resp = $this->esClient->search($params);
        } catch (\Exception $e) {
            app()->getFeLogger()->warning(get_called_class() . ' fail', [
                'exception' => $e
            ]);

            $resp = false;
        }

        if (
            !!$resp &&
            $resp['_shards']['successful'] &&
            !$resp['_shards']['failed'] &&
            !$resp['timed_out']
        ) {
            $resp = json_decode(json_encode($resp)); //to avoid changing all the references below
            $list = $resp->suggest->ac[0]->options;

            app()->getFeLogger()->info('Request to ' . get_called_class() . ' Autocomplete Search', [
                'elinput'  => $this->getTerm(),
                'eloutput' => [
                    'resultCount' => count($list)
                ]
            ]);

            foreach ($list as $result) {
                $result = $result->_source;

                // default for older data in ES
                if (!property_exists($result, 'reftype')) {
                    $result->reftype = 'c';
                    $result->cities = [];
                }

                $this->processMatch($result);
            }
        }

        return $this->matches->count() > 0;
    }

    protected function processMatch(\stdClass $result) {
        $this->matches[] = new Match(
            strtolower(\Search\Input::$typeMapping[$result->reftype]),
            $result->reftype. '.' .$result->refid,
            property_exists($result->names, explode('_', app()->getLocale())[0]) ?
                $result->names->{explode('_', app()->getLocale())[0]} : $result->alternate,
            $result->loc->lat,
            $result->loc->lon,
            $result->population,
            $result->country,
            $this
        );
    }

}