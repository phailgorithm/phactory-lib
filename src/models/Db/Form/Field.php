<?php namespace Db\Form;

use Model;

use Exception\NotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

use Phalcon\Forms\Element;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\TextArea;
use Phalcon\Validation\ValidatorInterface;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\StringLength;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\File;


class Field extends Model implements Arrayable {

    public static function getSource() : string { return 'form_field'; }

    private $required;

    protected $parent_form,
              $id,
              $name,
              $label,
              $data_type,
              $default_value,
              $placeholder,
              $attributes,
              $validators;


    public function __construct(array $data = array()) {
        parent::__construct($data);
        $this->validators = json_decode($this->validators, true);
        $this->attributes = json_decode($this->attributes ?? '[]', true);
    }


    public function isMultipart() : bool {
        return $this->data_type == 'image';
    }

    public function getName() : string {
        return $this->name;
    }

    public function addAttribute(string $name, string $value) {
        $this->attributes[] = [
            'name' => $name,
            'value' => $value
        ];
        return $this;
    }
    public function getAttribute( string $key ) : ?string {
        foreach ($this->attributes as $v) {
            if ($key === $v['name']) {
                return $v['value'];
            }
        }
        return null;
    }

    public function getId() : string {
        return sprintf('id_%s_%d', $this->parent_form, $this->id);
    }


       // foreach ($this->attributes as $v) {
       //      $field->setAttributes(array_merge($field->getAttributes(), [
       //          $v['name'] => $v['value']
       //      ]));
       //  }

    public function getValidators() : Collection {
        $return = new Collection();

        foreach ($this->validators as $i => $v) {
            $id = sprintf('validator_%s_%s_%s', $v['validator'], $v['name'], $this->getId());
            $this->validators[$i]['error'] = $errorMessage = di()->getTwig()->renderTemplate(
                !!$v['error'] ? $v['error'] : 'error: {{ field }}',array_merge($v,[
                    'field' => $this->label ?: $this->getName()
                ])
            );


            switch ($v['validator']) {
                case 'Captcha':
                    $return->push(
                        new Regex(array(
                            'pattern' => '/^'. session()->get('captcha') .'$/',
                            'message' => $errorMessage
                        )));
                    break;


                case 'Email':
                    $return->push(
                        new Regex(array(
                            'pattern' => '/[A-z0-9._%+-]+@[A-z0-9.-]+\.[A-z]{2,}$/',
                            'message' => $errorMessage,

                            # For frontend encoder
                            'validator' => 'pattern'
                        )));
                    break;

                case 'Regex':
                    $return->push(
                        new Regex(array(
                            'pattern' => '/'.$v['pattern'].'/',
                            'message' => $errorMessage,

                            # For frontend encoder
                            'validator' => 'pattern'
                        )));

                    break;

                case 'PresenceOf':
                    $this->required = true;
                    // $field->setAttributes(array_merge($field->getAttributes(), [
                    //     'required' => true
                    // ]));

                    $return->push(
                        new PresenceOf(array(
                            'message' => $errorMessage
                        )));

                    break;

                case 'StringLength':
                    if (!!$v['min']) {
                        $return->push(
                            new StringLength(array(
                                'min'            => $v['min'],
                                'messageMinimum' => $errorMessage,

                                # For frontend encoder
                                'ref'       => 'messageMinimum',
                                'validator' => 'min'
                            )));
                    }
                    if (!!$v['max']) {
                        $return->push(
                            new StringLength(array(
                                'max'            => $v['max'],
                                'messageMinimum' => $errorMessage,

                                # For frontend encoder
                                'ref'       => 'messageMaximum',
                                'validator' => 'max'
                            )));
                    }

                    break;
                case 'FileSize':
                    if (!!$v['max_size']) {

                        $this->validators[$i]['error'] = $errorMessage = di()->getTwig()->renderTemplate(
                            !!$v['error'] ? $v['error'] : 'error: {{ field }} filesize: :size - max: {{ max_size }}Mb',array_merge($v,[
                                'field' => $this->label ?: $this->getName()
                            ])
                        );

                        $return->push(
                            new File(array(
                                'maxSize' => sprintf('%dM', $v['max_size']),
                                'messageSize' => $errorMessage,
                                'ref' => 'messageSize',
                                'validator' => 'maxSize'

                            )));
                    }
                    break;

                case 'FileType':
                    if (!!$v['allowed_types']) {
                        $this->validators[$i]['error'] = $errorMessage = di()->getTwig()->renderTemplate(
                            !!$v['error'] ? $v['error'] : 'error: {{ field }} filetype: :type - allowed: {{ allowed_types }}',array_merge($v,[
                                'field' => $this->label ?: $this->getName()
                            ])
                        );

                        $return->push(
                            new File(array(
                                // 'maxSize' => '100M',
                                // 'messageSize' => 'err',
                                'allowedTypes' => explode(',', $v['allowed_types']),
                                'messageType' => $errorMessage,
                                'ref' => 'messageType',
                                'validator' => 'allowedTypes'
                            )));
                    }
                    break;
/**
        [
            "maxSize"              => "2M",
            "messageSize"          => ":field exceeds the max file size (:size)",
            "allowedTypes"         => [
                "image/jpeg",
                "image/png",
            ],
            "messageType"          => "Allowed file types are :types",
            "maxResolution"        => "800x600",
            "messageMaxResolution" => "Max resolution of :field is :resolution",
        ]

 */


            }
        }


        return $return;
    }

    public function toArray($columns = array()) : array {
        $validators = $this->getValidators();

        // d($validators);

        array_walk($this->validators, function(&$e) {
            unset($e['id'], $e['sort'], $e['form_field']);
            $e['for'] = $this->getId();
        });

        # Removing null values
        $filteredValidators = [];
        foreach ($this->validators as $validator) {
            $x = array_filter($validator, function($f) {
                return !is_null($f);
            });

            foreach ($this->attributes as $a) {
                if ($a['name'] == 'error-target') {
                    $x[ $a['name'] ] = di()->getTwig()->renderTemplate( $a['value'], [ 'id' => $this->getId() ]);
                }
            }
            $filteredValidators[] = $x;
        }

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'type' => $this->data_type,
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'defaultValue' => $this->default_value,
            // 'input' => $field->render(),
            'validators' => $filteredValidators,
            'attributes' => $this->attributes,
            'required' => $this->required
            // 'required' => $field->getAttribute('required', false)
        ];
    }
}
