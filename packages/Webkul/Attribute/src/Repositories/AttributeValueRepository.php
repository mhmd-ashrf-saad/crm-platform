<?php

namespace Webkul\Attribute\Repositories;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Attribute\Contracts\Attribute;
use Webkul\Attribute\Contracts\AttributeValue;
use Webkul\Core\Eloquent\Repository;
use Webkul\Core\Traits\Sanitizer;

class AttributeValueRepository extends Repository
{
    use Sanitizer;

    /**
     * Extensions that are never stored as an attribute value, whatever the upload claimed to be.
     *
     * Attribute values are written to the public disk and handed back by the web server, so a file
     * kept here is same-origin content: a stored `.html` renders the markup it carries, and a stored
     * `.php` is offered to the PHP handler. The form requests are the first gate, but not every
     * request that reaches this repository passes through one -- the lead forms carry no rule at
     * all for file and image attributes -- so the check is repeated where the file is actually
     * written instead of being trusted to the caller.
     *
     * `svg` is deliberately absent from this list: it is rendered as an image rather than executed,
     * so it is cleaned in place by sanitizeSvg() and kept.
     */
    public const EXECUTABLE_EXTENSIONS = [
        'asp', 'aspx', 'bat', 'cgi', 'cjs', 'cmd', 'com', 'exe', 'htaccess', 'htm', 'html', 'htx',
        'jar', 'js', 'jsp', 'jspx', 'mjs', 'phar', 'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'pht', 'phtml', 'pl', 'ps1', 'py', 'rb', 'sh', 'shtml', 'vbs', 'war', 'xhtm', 'xhtml',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return AttributeValue::class;
    }

    /**
     * Save attribute value.
     */
    public function save(array $data, $attributes = []): void
    {
        if (empty($attributes)) {
            $conditions = ['entity_type' => $data['entity_type']];

            if (isset($data['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributes = $this->attributeRepository->where($conditions)->get();
        }

        foreach ($attributes as $attribute) {
            $typeColumn = $this->model::$attributeTypeFields[$attribute->type];

            if ($attribute->type === 'boolean') {
                $data[$attribute->code] = isset($data[$attribute->code]) && $data[$attribute->code] ? 1 : 0;
            }

            if (! array_key_exists($attribute->code, $data)) {
                continue;
            }

            if ($attribute->type === 'price'
                && isset($data[$attribute->code])
                && $data[$attribute->code] === ''
            ) {
                $data[$attribute->code] = null;
            }

            if ($attribute->type === 'date' && $data[$attribute->code] === '') {
                $data[$attribute->code] = null;
            }

            if ($attribute->type === 'multiselect' || $attribute->type === 'checkbox') {
                $data[$attribute->code] = implode(',', $data[$attribute->code]);
            }

            if ($attribute->type === 'email' || $attribute->type === 'phone') {
                $data[$attribute->code] = $this->sanitizeEmailAndPhone($data[$attribute->code]);
            }

            if ($attribute->type === 'image' || $attribute->type === 'file') {
                $file = $data[$attribute->code] instanceof UploadedFile
                    ? $data[$attribute->code]
                    : null;

                $data[$attribute->code] = $file
                    ? $file->store($data['entity_type'].'/'.$data['entity_id'])
                    : null;

                if ($file && $data[$attribute->code]) {
                    $data[$attribute->code] = $this->keepInertUpload($data[$attribute->code], $file);
                }
            }

            $attributeValue = $this->findOneWhere([
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'attribute_id' => $attribute->id,
            ]);

            if (! $attributeValue) {
                $this->create([
                    'entity_type' => $data['entity_type'],
                    'entity_id' => $data['entity_id'],
                    'attribute_id' => $attribute->id,
                    $typeColumn => $data[$attribute->code],
                ]);
            } else {
                $this->update([
                    $typeColumn => $data[$attribute->code],
                ], $attributeValue->id);

                if ($attribute->type == 'image' || $attribute->type == 'file') {
                    if ($attributeValue->text_value) {
                        Storage::delete($attributeValue->text_value);
                    }
                }
            }
        }
    }

    /**
     * Keep a stored upload only when the browser and the web server will treat it as data.
     *
     * Returns the stored path, or null after deleting an upload that would have been served as
     * active content -- the attribute then holds no value at all rather than a URL to a payload.
     */
    protected function keepInertUpload(string $path, UploadedFile $file): ?string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::EXECUTABLE_EXTENSIONS, true)) {
            Storage::delete($path);

            return null;
        }

        $this->sanitizeSvg($path, $file);

        return $path;
    }

    /**
     * Is value unique.
     *
     * @param  int  $entityId
     * @param  string  $entityType
     * @param  Attribute  $attribute
     * @param  string  $value
     * @return bool
     */
    public function isValueUnique($entityId, $entityType, $attribute, $value)
    {
        $query = $this->resetScope()->model
            ->where('attribute_id', $attribute->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', '!=', $entityId);

        /**
         * If the attribute type is email or phone, check the JSON value.
         */
        if (in_array($attribute->type, ['email', 'phone'])) {
            $query->whereJsonContains($this->model::$attributeTypeFields[$attribute->type], [['value' => $value]]);
        } else {
            $query->where($this->model::$attributeTypeFields[$attribute->type], $value);
        }

        return $query->get()->count() ? false : true;
    }

    /**
     * Removed null values from email and phone fields.
     *
     * @param  array  $data
     * @return array
     */
    public function sanitizeEmailAndPhone($data)
    {
        foreach ($data as $key => $row) {
            if (is_null($row['value'])) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Replace placeholders with values
     */
    public function getAttributeLabel(mixed $value, mixed $attribute)
    {
        switch ($attribute?->type) {
            case 'price':
                $label = core()->formatBasePrice($value);

                break;

            case 'boolean':
                $label = $value ? 'Yes' : 'No';

                break;

            case 'select':
            case 'radio':
            case 'lookup':
                if ($attribute->lookup_type) {
                    $option = $this->attributeRepository->getLookUpEntity($attribute->lookup_type, $value);
                } else {
                    $option = $attribute->options->where('id', $value)->first();
                }

                $label = $option?->name;

                break;

            case 'multiselect':
            case 'checkbox':
                if ($attribute->lookup_type) {
                    $options = $this->attributeRepository->getLookUpEntity($attribute->lookup_type, explode(',', $value));
                } else {
                    $options = $attribute->options->whereIn('id', explode(',', $value));
                }

                $optionsLabels = [];

                foreach ($options as $key => $option) {
                    $optionsLabels[] = $option->name;
                }

                $label = implode(', ', $optionsLabels);

                break;

            case 'email':
            case 'phone':
                if (! is_array($value)) {
                    break;
                }

                $optionsLabels = [];

                foreach ($value as $item) {
                    $optionsLabels[] = $item['value'].' ('.$item['label'].')';
                }

                $label = implode(', ', $optionsLabels);

                break;

            case 'address':
                if (
                    ! $value
                    || ! count(array_filter($value))
                ) {
                    break;
                }

                $label = $value['address'].'<br>'
                    .$value['postcode'].'  '.$value['city'].'<br>'
                    .core()->state_name($value['state']).'<br>'
                    .core()->country_name($value['country']).'<br>';

                break;

            case 'date':
                if ($value) {
                    $label = Carbon::parse($value)->format('D M d, Y');
                } else {
                    $label = null;
                }

                break;

            case 'datetime':
                if ($value) {
                    $label = Carbon::parse($value)->format('D M d, Y H:i A');
                } else {
                    $label = null;
                }

                break;

            default:
                if ($value instanceof Carbon) {
                    $label = $value->format('D M d, Y H:i A');
                } else {
                    $label = $value;
                }

                break;
        }

        return $label ?? null;
    }
}
