<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Core\Contracts\Validations\Decimal;
use Webkul\Core\Contracts\Validations\SignedDecimal;

class AttributeForm extends FormRequest
{
    /**
     * The `attributes.validation` value that selects the signed decimal rule.
     *
     * One attribute carries it: the quote's `adjustment_amount`, which exists to take money off an
     * offer and is therefore the only money field in the product where a negative is meant. Every
     * other `price` attribute keeps the non-negative `Decimal` rule, so this value is deliberately
     * absent from the attribute screen's input-validation picker (which renders for `text`
     * attributes only) and from the whitelist in `AttributeController`: nothing can select it from
     * the UI, and the seeded attribute is the only row that holds it.
     */
    public const SIGNED_DECIMAL = 'signed_decimal';

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
                    $attribute->code.'.state' => $this->countryOffersStateList($attribute->code) ? 'required' : 'nullable',
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

                /**
                 * A `price` attribute is a money field, so it is non-negative by default -- a
                 * price, a discount, a tax or a total has no negative form. The one exception is
                 * the quote's `adjustment_amount`, which subtracts from the offer and whose stored
                 * value is `-1500.0000` in the demo tenant; it opts in by carrying this validation
                 * value, and it is the only attribute that does. Nothing else widens: a `price`
                 * attribute with any other validation value still gets `Decimal`.
                 */
                if ($attribute->type == 'price') {
                    array_push($validations[$attribute->code], $attribute->validation === self::SIGNED_DECIMAL
                        ? new SignedDecimal
                        : new Decimal
                    );
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

    /**
     * Whether the address form can offer a state list for the submitted country.
     *
     * The address component renders the region field as a `<select>` fed from `country_states` when
     * the chosen country has rows there, and as a free-text input when it has none -- and 239 of the
     * 255 seeded countries have none, so the free-text branch is the common case, not the exception.
     * Requiring the field for every country therefore demanded a value the form had no way to
     * supply, which is what stopped every seeded quote (all of them for Egypt, all of them carrying
     * an empty region) from being saved at all.
     *
     * The requirement is kept where it can be met: a country that has a list still rejects a missing
     * region. The list is read through `core()->states()`, the same repository `groupedStatesByCountries()`
     * -- and therefore the component's `haveStates()` -- is built from, so the rule and the rendered
     * field can never disagree about whether the country has a list.
     */
    protected function countryOffersStateList(string $attributeCode): bool
    {
        $countryCode = (string) $this->input($attributeCode.'.country');

        return $countryCode !== '' && core()->states($countryCode)->isNotEmpty();
    }
}
