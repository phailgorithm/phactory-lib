<?php namespace Core\Autocomplete\Backends;

use Core\Autocomplete\Backend;
use Core\Autocomplete\Match;
use Illuminate\Support\Collection;

class OpenStreetMaps extends Backend {

    protected function transformEntry(\stdClass $entry) : Match {

        if (strlen($entry->display_name) < 40) {
            $name = $entry->display_name;
        } else {
            $name = (!empty($entry->address->city)) ?
                $entry->address->city . ', ' . $entry->address->country
                :
                $entry->address->county . ', ' . $entry->address->country;
        }

        return new Match(
            sprintf('o.%s.%s', $this->getLocale(), $entry->place_id),[
                '_d' => $entry,
                'name' => $name,
                'coords' => [
                    floatval($entry->lat),
                    floatval($entry->lon),
                ],
                'importance' => $entry->importance,
                'country_code' => $entry->address->country_code,
            ],
            $this
        );
    }

    public function run() : Collection {
        $matches = new Collection;
        try {
            foreach ($this->get() as $entry) {
                $matches[] = $this->transformEntry($entry);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $matches;;
    }

    private function get() {
        $input  = $this->getTerm();
        $locale = $this->getLocale();

        $url = str_replace(
            array('{{input}}', '{{locale}}'),
            array($input, $locale),
            $this->options['url']
        );

        $result = new Collection;

        # if an error happens from here, it'll be reported in the parent Autocomplete class
        $req = \Requests::get($url, ['User-Agent' => $this->options['ua'] ], ['timeout' => 5]);

        if ($req->success) {
            $data = json_decode($req->body);
            foreach ($data as $p) {
                # ignore coordinates-less entries
                if (
                    !property_exists($p, 'lat') || !property_exists($p, 'lon') ||
                    is_null($p->lat) || is_null($p->lon)
                ) {
                    continue;
                }
                $result->push($p);
            }
        }

        return $result;
    }
}