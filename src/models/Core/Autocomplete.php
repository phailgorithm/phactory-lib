<?php namespace Core;

use Transliterator;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

class Autocomplete implements Arrayable {

    protected $input, $locale, $matches, $term = null;

    public function __construct(string $input, string $locale, Collection $matches = null) {
        $this->input   = $input;
        $this->locale  = $locale;
        $this->matches = $matches ?? new Collection;
    }

    public function getInput() : string {
        return $this->input;
    }

    public function getLocale() : string {
        return $this->locale;
    }


    /**
     * Implements logic to handle when to stop calling multiple backends
     *
     * @return bool
     */
    protected function canRun() : bool {
        return $this->matches->count() == 0;
    }

    public function exec() : Autocomplete {
        foreach (conf()->autocompleteBackends as $be => $options) {
            # @TODO - Apply fallback or merge logic
            $class = 'Core\\Autocomplete\\Backends\\'. $be;

            $be = new $class($this, $options->toArray());

            if ($this->canRun()) {
                $this->matches = $this->matches->merge($be->run())->unique();
            }
        }

        # Removing duplicate matches
        $this->matches = $this->matches->unique(function($m) {
            return $m->getId();
        });

        return $this;
    }

    public function getMatches() : Collection {
        return $this->matches;
    }

    public function getTerm($original = false) : string {
        $term = strtolower(trim(urldecode($this->input)));

        if ($original) {
            return $term;

        } else if (is_null($this->term)) {
            $transliterator = Transliterator::createFromRules(':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;', Transliterator::FORWARD);

            $this->term = $transliterator->transliterate($term);
        }

        return $this->term;
    }

    public function toArray() : array {
        return $this->matches->toArray();
    }

}
