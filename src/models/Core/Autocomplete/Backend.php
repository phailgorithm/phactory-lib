<?php namespace Core\Autocomplete;

use Transliterator;
use Core\Autocomplete;
use Illuminate\Support\Collection;

class Backend {

	protected $autocomplete, $options;

    public function __construct(Autocomplete $ac, array $options = array()) {
        $this->autocomplete = $ac;
        $this->options  	= $options;
    }

    public function getLocale() : string {
        return $this->autocomplete->getLocale();
    }

    public function getTerm($original = false) : string {
        $term = strtolower(trim(urldecode($this->autocomplete->getInput())));

        if ($original) {
            return $term;

        } else {
            $transliterator = Transliterator::createFromRules(
            	':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
            	Transliterator::FORWARD
            );
            return $transliterator->transliterate($term);
        }
    }

    public function getName() : string {
        $str = get_called_class();
        return preg_replace('/[a-z]/', '', substr($str, strrpos($str, '\\') + 1));
    }
}
