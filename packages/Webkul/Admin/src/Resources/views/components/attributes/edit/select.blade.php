@php
    $options = $attribute->lookup_type
        ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
        : $attribute->options()->orderBy('sort_order')->get();
@endphp

<x-admin::form.control-group.control
    type="select"
    id="{{ $attribute->code }}"
    name="{{ $attribute->code }}"
    rules="{{ $validations }}"
    :label="$attribute->name"
    :placeholder="$attribute->name"
    :value="old($attribute->code) ?? $value"
>
    {{--
        A native select renders blank when no option is selected and there is no empty option, so every
        attribute select (lead source, lead type, sales owner, ...) opened as an empty box with nothing
        to read. The placeholder is the shipped generic `common.custom-attributes.select` key (اختر),
        the same one the country/state selects use from the same key group.
    --}}
    <option value="">@lang('admin::app.common.custom-attributes.select')</option>

    @foreach ($options as $option)
        <option value="{{ $option->id }}">
            {{ $option->name }}
        </option>
    @endforeach
</x-admin::form.control-group.control>