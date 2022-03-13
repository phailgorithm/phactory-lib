<?php namespace Features;

use Model;
use Db\Form\Field;

use Gregwar\Captcha\{PhraseBuilder, CaptchaBuilder};

use Exception\NotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Phalcon\Validation;

class Form extends Model implements Arrayable {

    protected $name,
              $status,
              $fields;

    private $captcha;

    public static function getSource() : string {
        return 'form';
    }

    public static function getInstance(string $name) : self {
        # Form metadata
        $object = self::findOne($name, 'name');

        $l = di()->getLocale();
        $object->fields = Field::query(
              sprintf('SELECT ? AS parent_form, A.id, A.name,
              MAX(B.%s) AS label,
              MAX(C.%s) AS placeholder,
              MAX(data_type) AS data_type,
              A.attributes,
              json_agg(V) AS validators
            FROM %s AS A
            LEFT JOIN i18n AS B ON (A.label = B.id)
            LEFT JOIN i18n AS C ON (A.placeholder = C.id)
            LEFT JOIN (
              SELECT
                FFV.id,
                FFV.sort,
                FFV.form_field,
                FFV.validator,
                FFV.min, FFV.max,
                FFV.max_size, FFV.allowed_types,
                FFV.pattern,
                FI.%s AS error
                FROM form_field_validator AS FFV
                LEFT JOIN i18n AS FI ON (FFV.error = FI.id)
                ORDER BY FFV.sort ASC
            ) AS V
            ON (V.form_field = A.id)
            WHERE form = ? GROUP BY A.id ORDER BY A.sort ASC', $l, $l, Field::getSource(), $l),
        [ $name, $object->id ]);

        return $object;
    }



    public function setCaptcha() {
        $builder = new CaptchaBuilder(null, new PhraseBuilder(rand(4,6), '0123456789') );
        $builder->build();
        $this->captcha = $builder->inline();
        return $builder->getPhrase();
    }


    /**
     * @return array
     */
    public function toArray($columns = array()) : array {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'fields' => $this->fields->toArray(),
            'captcha' => $this->captcha
        ];
    }

    /**
     * List of fields in this form
     *
     * @return Collection
     */
    public function getFields() : Collection {
        return $this->fields;
    }

    /**
     * Exectutes all validators against input
     *
     * @return countable
     */
    public function validate(array $data = array()) : \Phalcon\Validation\Message\Group {
        $validation = new Validation();
        foreach ($this->fields as $field) {
            foreach ($field->getValidators() as $validator) {
                $validation->add(
                    $field->getName(),
                    $validator
                );
            }
        }
        return $validation->validate($data);
    }

    /**
     * Workaround utility to save images to directs
     *
     * @return
        {
            "id": "3dcf4ee8-f931-4628-b8cd-cec53b078f31",
            "storage": "local",
            "filename_disk": "3dcf4ee8-f931-4628-b8cd-cec53b078f31.jpg",
            "filename_download": "tnbzb.jpg",
            "title": "tnbzb.jpg",
            "type": "image/jpeg",
            "folder": "b86008dc-2d59-45f0-8db7-126f89715e4e",
            "uploaded_by": "f95de2fd-dc17-4fa4-947a-3c9d9d785574",
            "uploaded_on": "2021-11-24T16:04:31.132Z",
            "modified_by": null,
            "modified_on": "2021-11-24T16:04:31.156Z",
            "charset": null,
            "filesize": "109527",
            "width": 900,
            "height": 675,
            "duration": null,
            "embed": null,
            "description": null,
            "location": null,
            "tags": null,
            "metadata": null
        }
     */
    protected function processImageToDirectus(string $index, string $folder = null) : ?array {

        $o = sprintf(
            'curl -s -H "Authorization: Bearer %s" %s -XPOST -F "folder=%s" -F "title=%s"  -F "file=@%s;type=%s;filename=%s"',
                $_ENV['WEB_DIRECTUS_FORM_TOKEN'],
                $_ENV['WEB_DIRECTUS_FORM_ENDPOINT'],
                $folder,
                $_FILES[$index]['name'],
                $_FILES[$index]['tmp_name'],
                $_FILES[$index]['type'],
                $_FILES[$index]['name']
        );
        $result = shell_exec($o);
        if (!$result) {
            throw new \Exception('Cannot save to directus : ' .json_encode($_FILES));
        }
        $result = json_decode($result, true);
        if (!$result || !isset($result['data'])) {
            throw new \Exception('Failed to save to directus: ' . json_encode($result));
        }
        return $result['data'];
    }


    public function saveData( array $data ) {

        foreach ( $this->fields as $field ) {
            if ($field->isMultipart()) {
                $upload = $this->processImageToDirectus( $field->getName(), $field->getAttribute('folder') );
                if (!$upload) {
                    throw new \Exception('processImageToDirectus() failed');
                }
                $data[ $field->getName() ] = $upload['id'];
            }
        }

        # Generates a list of [ :named, :fields ]
        $fields = $this->fields->map(function(Field $e) {
            return ':'.$e->getName();
        })->toArray();

        $values = array();

        # Generates an hashmap of { name => value }
        foreach ($fields as $f) {
            $k = ltrim($f,':');
            if (!isset($values[$k])) {
                $values[ $k ] = $data[$k] ?? null;
            }
        }

        $sql = "INSERT INTO " . $this->name . " (" . implode(',',array_keys($values)) . ") VALUES (". implode(',',$fields) .")";

        return di()->getDb()->query($sql, $values);
    }


}
