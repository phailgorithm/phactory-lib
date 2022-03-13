<?php namespace \Phailgorithm\PhactoryLib\Model;

use Phailgorithm\PhactoryLib\Model;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;


/**
 * Markdown format content held in `static_content` table
 */
class StaticContent extends Model implements Arrayable {

    protected $content;

    protected static function getSource() : string { return 'static_content'; }

    public function __construct(string $slug, string $website = null) {
        $ttl = 1;
        $website = is_null($website) ? di()->getDomain() : $website;
        $item = static::cachedRawQuery(
            'static-content:'. $slug,
            $ttl,
            "SELECT content
             FROM " . static::getSource() .
             " WHERE slug = ?
              AND website = (SELECT id FROM website WHERE domain = ?)"
             , [
                $slug, $website ])->first();

        if (!$item) {
            throw new \Phailgorithm\PhactoryLib\Exception\NotFound("Content not found: ${website} / ${slug}");
        }
        $Parsedown = new \Parsedown();
        $this->content = htmlspecialchars_decode($Parsedown->text($item['content'] ));;
    }


    public static function list(string $domain = null) : Collection {
        $ttl = 1;
        $domain = is_null($domain) ? di()->getDomain() : $domain;
        return static::cachedRawQuery(
            'static-content-list:'. $domain,
            $ttl,
            "SELECT name, slug
             FROM " . static::getSource() .
             " WHERE status = 'published' AND website = (SELECT id FROM website WHERE domain = ?)"
             , [
                $domain ]);

    }



    public function toString() : string {
        return $this->content;
    }


    public function toArray($columns = null) : array {
        return [
            'content' => $this->content,
        ];
    }
}
