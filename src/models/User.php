<?php namespace \Phailgorithm\PhactoryLib\Model;

use Phailgorithm\PhactoryLib\Model;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

class User extends Model implements Arrayable {

    protected static function getSource() : string { return 'public.user'; }

    public $name, $email, $password, $id;

    // public function __construct(string $id, array $input = array(), int $seed = null, int $ttl = 0, array $spinner = null) {
    //     $this->id = explode(':',$id,2);
    //     $this->locale = is_null($locale) ? di()->getLocale() : $locale;
    //     $this->seed = is_null($seed) ? crc32($this->id[1]) : intval($seed);
    //     $this->input = $input;
    //     $this->items = new Collection();
    //     $this->ttl = $ttl;
    //     if (is_null($spinner)) {
    //         $this->fetchItems();
    //     } else {
    //         foreach (['merge', 'token_count', 'condition', 'items'] as $k) {
    //             $this->$k = $spinner[$k];
    //         }
    //     }


    //     return $this;
    //  }


    public static function create(string $name, string $email, string $password) {
        $password = password_hash($password, PASSWORD_ARGON2I, [
                        'memory_cost' => 4096, 'time_cost' => 3, 'threads' => 1]);

        $query = di()->getDb()->query(sprintf('INSERT INTO %s (name, email, password, date_created, date_seen) VALUES (?,?,?, NOW(), NOW());', static::getSource()), [
            $name,
            $email,
            $password
        ]);
    }


    public static function upsert(string $name, string $email, array $metadata = array()) {
        $q = di()->getDb()->query(
                sprintf('
                    INSERT INTO %s (name, email, metadata, date_seen, date_created) VALUES (?,?,?, NOW(), NOW())
                    ON CONFLICT (email) DO
                    UPDATE SET
                        date_seen = NOW(),
                        metadata = EXCLUDED.metadata,
                        name = EXCLUDED.name
                    RETURNING id;', static::getSource()), [
            $name,
            $email,
            json_encode($metadata)
        ]);
        $q->fetch(\PDO::FETCH_ASSOC);
    }

}
