<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Core\Contracts\Validations\Decimal;

class AttributeForm extends FormRequest
{
    /**
     * Extensions a `file` attribute accepts.
     *
     * A file attribute is a document field -- signed contracts, scanned invoices, spreadsheets --
     * and it is written to the public disk, which the web server hands back verbatim. The list is
     * therefore an allowlist rather than a blocklist: anything the browser would render or execute
     * (html, svg, js) and anything the PHP handler would accept (php, phtml) is absent by
     * construction, and a format that nobody thought to ban cannot slip through. SVG is excluded
     * on purpose even though sanitizeSvg() would clean it -- a document has no need for it.
     *
     * The extension is what is checked, and Laravel resolves it from the file's own content, so
     * renaming a payload does not get past the rule.
     */
    public const FILE_EXTENSIONS = [
        'csv', 'doc', 'docx', 'ods', 'odt', 'pdf', 'ppt', 'pptx', 'rtf', 'txt', 'xls', 'xlsx', 'zip',
    ];

    /**
     * @var array
     */
    protected $rules = [];

    /**
     * Create a new form request instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository
    ) {}

    /**
     * Determine if the product is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $attributes = $this->attributeRepository->scopeQuery(function ($query) {
            $query = $query->whereIn('code', array_keys(request()->all()))
                ->where('entity_type', request('entity_type'));

            if (request()->has('quick_add')) {
                $query = $query->where('quick_add', 1);
            }

            return $query;
        })->get();

        foreach ($attributes as $attribute) {
            $validations = [];

            if ($attribute->type == 'boolean') {
                continue;
            } elseif ($attribute->type == 'address') {
                if (! $attribute->is_required) {
                    continue;
                }

                $validations = [
                    $attribute->code.'.address' => 'required',
                    $attribute->code.'.country' => 'required',
                    $attribute->code.'.state' => 'required',
                    $attribute->code.'.city' => 'required',
                    $attribute->code.'.postcode' => 'required',
                ];
            } elseif ($attribute->type == 'email') {
                $validations = [
                    $attribute->code => [$attribute->is_required ? 'required' : 'nullable'],
                    $attribute->code.'.*.value' => [$attribute->is_required ? 'required' : 'nullable', 'email'],
                    $attribute->code.'.*.label' => $attribute->is_required ? 'required' : 'nullable',
                ];
            } elseif ($attribute->type == 'phone') {
                $validations = [
                    $attribute->code => [$attribute->is_required ? 'required' : 'nullable'],
                    $attribute->code.'.*.value' => [$attribute->is_required ? 'required' : 'nullable'],
                    $attribute->code.'.*.label' => $attribute->is_required ? 'required' : 'nullable',
                ];
            } else {
                $validations[$attribute->code] = [$attribute->is_required ? 'required' : 'nullable'];

                if ($attribute->type == 'text' && $attribute->validation) {
                    array_push($validations[$attribute->code],
                        $attribute->validation == 'decimal'
                        ? new Decimal
                        : $attribute->validation
                    );
                }

                if ($attribute->type == 'price') {
                    array_push($validations[$attribute->code], new Decimal);
                }

                if ($attribute->type == 'image' && ! request($attribute->code.'.delete')) {
                    array_push($validations[$attribute->code], 'mimes:bmp,jpeg,jpg,png,webp');
                }

                /**
                 * A `file` attribute used to reach the else branch above and collect no rule at
                 * all, so any upload at all was accepted and stored on the public disk.
                 */
                if ($attribute->type == 'file' && ! request($attribute->code.'.delete')) {
                    array_push($validations[$attribute->code], 'mimes:'.implode(',', self::FILE_EXTENSIONS));
                }
            }

            if ($attribute->is_unique) {
                array_push($validations[in_array($attribute->type, ['email', 'phone'])
                    ? $attribute->code.'.*.value'
                    : $attribute->code
                ], function ($field, $value, $fail) use ($attribute) {
                    if (! $this->attributeValueRepository->isValueUnique($this->id, $attribute->entity_type, $attribute, request($field))) {
                        $fail('The value has already been taken.');
                    }
                });
            }

            $this->rules = array_merge($this->rules, $validations);
        }

        return $this->rules;
    }
}
