@php
    if (! empty($value)) {
        if ($value instanceof \Carbon\Carbon) {
            $value = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            $value = \Carbon\Carbon::parse($value)->format('Y-m-d');
        }
    }

    /*
     * The inline control prints `value` verbatim, which is the ISO value both the date input and
     * the save request need -- so a `date` attribute on a record read `2026-11-25` while every
     * grid and every other date on the screen went through `core()->formatDate()`. `value-label`
     * is the display slot the price, text and lookup views already use, and `d M Y` is the
     * date-only format the leads and quotes grids print a date-only column with.
     *
     * `core()->formatDate(null)` parses to *now*, so a value-less attribute renders nothing.
     */
    $valueLabel = empty($value) ? '' : core()->formatDate($value, 'd M Y');
@endphp

<x-admin::form.control-group.controls.inline.date
    ::name="'{{ $attribute->code }}'"
    ::value="'{{ $value }}'"
    rules="required"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
    ::value-label="'{{ $valueLabel }}'"
    {{--
        After an inline save the control would keep showing the label rendered here, so the
        display is re-formatted from the saved value through the product's own JS date
        formatter -- the same `d M Y` shape, in the interface locale. The round trip is parsed
        as a local midnight so a date cannot slip a day in a timezone behind UTC.
    --}}
    ::value-formatter="(value) => value ? $admin.formatDate(String(value) + 'T00:00:00', 'd MMM yyyy') : ''"
/>
