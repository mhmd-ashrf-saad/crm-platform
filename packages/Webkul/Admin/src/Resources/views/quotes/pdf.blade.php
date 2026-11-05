<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html
    lang="{{ $locale = app()->getLocale() }}"
    dir="{{ in_array($locale, ['fa', 'ar']) ? 'rtl' : 'ltr' }}"
>
    <head>
        <!-- meta tags -->
        <meta
            http-equiv="Cache-control"
            content="no-cache"
        >

        <meta
            http-equiv="Content-Type"
            content="text/html; charset=utf-8"
        />

        @php
            /**
             * The direction the tables have to read in. mpdf's `SetDirectionality()` only recognises
             * the literal `rtl` (the PDF layer hands it the locale, so `ar` left the engine in LTR),
             * and a table takes its own direction from the block it sits in — so `<body dir="ar">`
             * gave every table the direction string `ar`, which is neither `rtl` nor `ltr`. The
             * header therefore stayed left-to-right inside a right-to-left page. This value is the
             * one valid direction the body and every table are given below, and `table.rtl` reads it
             * back in CSS so the mirroring does not depend on the body inheriting.
             */
            $direction = in_array($locale, ['ar', 'fa']) ? 'rtl' : 'ltr';

            if ($locale == 'en') {
                $fontFamily = [
                    'regular' => 'DejaVu Sans',
                    'bold' => 'DejaVu Sans',
                ];
            }  else {
                $fontFamily = [
                    'regular' => 'Arial, sans-serif',
                    'bold' => 'Arial, sans-serif',
                ];
            }

            if (in_array($locale, ['ar', 'fa', 'tr'])) {
                $fontFamily = [
                    'regular' => 'DejaVu Sans',
                    'bold' => 'DejaVu Sans',
                ];
            }
        @endphp

        <!-- lang supports inclusion -->
        <style type="text/css">
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: {{ $fontFamily['regular'] }};
            }

            body {
                font-size: 10px;
                color: #091341;
                font-family: "{{ $fontFamily['regular'] }}";
            }

            b, th {
                font-family: "{{ $fontFamily['bold'] }}";
            }

            .page-content {
                padding: 12px;
            }

            .page-header {
                border-bottom: 1px solid #E9EFFC;
                text-align: center;
                font-size: 24px;
                text-transform: uppercase;
                color: #000DBB;
                padding: 24px 0;
                margin: 0;
            }

            .logo-container {
                position: absolute;
                top: 20px;
                left: 20px;
            }

            .logo-container.rtl {
                left: auto;
                right: 20px;
            }

            .logo-container img {
                max-width: 100%;
                height: auto;
            }

            .page-header b {
                display: inline-block;
                vertical-align: middle;
            }

            .small-text {
                font-size: 7px;
            }

            table {
                width: 100%;
                border-spacing: 1px 0;
                border-collapse: separate;
                margin-bottom: 16px;
            }
            
            table thead th {
                background-color: #E9EFFC;
                color: #000DBB;
                padding: 6px 18px;
                text-align: left;
            }

            table.rtl {
                direction: rtl;
            }

            table.rtl thead tr th {
                text-align: right;
            }

            table tbody td {
                padding: 9px 18px;
                border-bottom: 1px solid #E9EFFC;
                text-align: left;
                vertical-align: top;
            }

            table.rtl tbody tr td {
                text-align: right;
            }

            .summary {
                width: 100%;
                display: inline-block;
            }

            .summary table {
                float: right;
                width: 250px;
                padding-top: 5px;
                padding-bottom: 5px;
                background-color: #E9EFFC;
                white-space: nowrap;
            }

            .summary table.rtl {
                width: 280px;
            }

            .summary table.rtl {
                margin-right: 480px;
            }

            .summary table td {
                padding: 5px 10px;
            }

            /*
                The longest figure label, `المجموع الكلي`, wrapped onto two lines: `المجموع` alone
                measures 30.67pt and `الكلي` 20.40pt, but the unwrapped label is 53.68pt and the
                column only gave its cells ~52.4pt of content width, so the label broke mid-phrase
                and only the grand total row grew a second line. The table cannot simply be widened:
                `margin-right: 480px` pins its right edge at x=219.53pt and its left edge already
                sits on the page content box at x=9.53pt, so 280px is the whole width the page has
                left for it. What mpdf gets wrong inside that width is the split — it handed the
                separator column 42pt (a `:` measures 2.53pt) while the label column fell 1.24pt
                short. `white-space: nowrap` is already declared on `.summary table` above, but
                mpdf reads it from the cell, not the table, so the label column kept wrapping.
                Declaring it on the cells makes the column's minimum width its widest label
                (68.68pt including padding) and lets the separator column give up the slack it does
                not need; the amount column loses 1.24pt and the table keeps its width, its numbers,
                its separator and its direction. Scoped to `.rtl` so the English document, sized for
                `Grand Total`, is untouched.
            */
            .summary table.rtl td:nth-child(1) {
                white-space: nowrap;
            }

            .summary table td:nth-child(2) {
                text-align: center;
            }

            .summary table td:nth-child(3) {
                text-align: right;
            }
        </style>
    </head>

    <body dir="{{ $direction }}">
        <div class="page">
            <!-- Header -->
            <div class="page-header">
                <b>@lang('admin::app.quotes.index.pdf.title')</b>
            </div>

            <div class="page-content">
                <!-- Invoice Information -->
                <table class="{{ $direction }}">
                    <tbody>
                        <tr>
                            <td style="width: 50%; padding: 2px 18px;border:none;">
                                <b>
                                    @lang('admin::app.quotes.index.pdf.quote-id'): 
                                </b>

                                {{--
                                    `#` is a direction-neutral character, so in this right-to-left
                                    paragraph it took the paragraph direction and printed to the
                                    right of the digit: `1#`. The isolate pair built below — U+2066
                                    LEFT-TO-RIGHT ISOLATE … U+2069 POP DIRECTIONAL ISOLATE — makes
                                    the token a left-to-right run of its own, so it prints `#1` here
                                    as it does in the English document; both are zero-width and mpdf
                                    consumes them while reordering. Only a right-to-left document
                                    needs them: with nothing to reorder, the English document would
                                    carry the two controls into its text layer for no gain, so it
                                    keeps the bare token. `$quote->id` is an integer, so the raw
                                    echo has nothing to escape.
                                --}}
                                @php
                                    $quoteReference = $direction === 'rtl'
                                        ? '&#x2066;#'.$quote->id.'&#x2069;'
                                        : '#'.$quote->id;
                                @endphp

                                <span>
                                    {!! $quoteReference !!}
                                </span>
                            </td>

                            <td style="width: 50%; padding: 2px 18px;border:none;">
                                <b>
                                    @lang('admin::app.quotes.index.pdf.person'):
                                </b>

                                <span>
                                    {{ $quote->person->name }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td style="width: 50%; padding: 2px 18px;border:none;">
                                <b>
                                    @lang('admin::app.quotes.index.pdf.sales-person'): 
                                </b>

                                <span>
                                    {{ $quote->user->name }}
                                </span>
                            </td>

                            <td style="width: 50%; padding: 2px 18px;border:none;">
                                <b>
                                    @lang('admin::app.quotes.index.pdf.subject'):
                                </b>

                                <span>
                                    {{ $quote->subject }}
                                </span>
                            </td>
                        </tr>
                        
                        <tr>
                            <td style="width: 50%; padding: 2px 18px;border:none;">
                                <b>
                                    @lang('admin::app.quotes.index.pdf.date'):
                                </b>

                                <span>
                                    {{--
                                        printed `2026-11-02` while the same field read `02 نوفمبر 2026` on
                                        screen. `d-m-Y` is not one of this product's formats: it is a
                                        bare ISO ordering that `core()->formatDate()` renders verbatim
                                        because it has no month name in it to translate. The on-screen
                                        value comes from `QuoteDataGrid`'s `expired_at` column, which is
                                        `core()->formatDate($row->expired_at, 'd M Y')` — the product's
                                        date-only format, shared with `LeadDataGrid`'s
                                        `expected_close_date` and `EventDataGrid`'s `date`. `d M Y` is what
                                        a date with no time of day must use: `formatDate()`'s default
                                        `d M Y h:i A` would bolt a clock onto a value that carries
                                        none, and this column stores a calendar day. So the PDF asks the
                                        same helper for the same format the screen asks for, and the
                                        locale translates the month name in both.
                                    --}}
                                    {{ core()->formatDate($quote->created_at, 'd M Y') }}
                                </span>
                            </td>

                            <td style="width: 50%; padding: 2px 18px;border:none;">
                                <b>
                                    @lang('admin::app.quotes.index.pdf.expired-at'):
                                </b>

                                <span>
                                    {{ core()->formatDate($quote->expired_at, 'd M Y') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                @php
                    /**
                     * Both address blocks read the same five keys, and every one of them can be
                     * absent: `state` and `city` are what a partial record loses first, an import
                     * leaves `postcode` and `country` blank, and a hand-edited payload can carry an
                     * array where a string belongs. `core()->country_name()` takes a `string`, so a
                     * non-string value there is a `TypeError`, and an empty value printed an empty
                     * line. Read each field with a null default, trim it, and keep it only when it
                     * has something to show — the way the on-screen address block does — so a line
                     * with nothing in it is skipped instead of carried.
                     */
                    $addressField = static function ($address, string $key): ?string {
                        $value = $address[$key] ?? null;

                        if (! is_scalar($value)) {
                            return null;
                        }

                        $value = trim((string) $value);

                        return $value === '' ? null : $value;
                    };

                    /**
                     * One printable line per row of the block, in the template's own order. The
                     * locality line joins only the half that is present, so a missing postcode never
                     * swallows the city and a missing city never leaves a dangling separator.
                     */
                    $addressLines = static function ($address) use ($addressField): array {
                        if (! is_array($address)) {
                            return [];
                        }

                        $country = $addressField($address, 'country');

                        /**
                         * `state` is stored as a code — `C` — and this block printed the code while
                         * the on-screen address block printed the name. Resolve it exactly the way
                         * that block does, `core()->state_name($code, $country)`, so the PDF says
                         * `القاهرة` too. The country is passed with it for the same reason it is
                         * there: a code that belongs to another country must not print this one's
                         * state name.
                         */
                        $state = $addressField($address, 'state');

                        return array_values(array_filter([
                            $addressField($address, 'address'),
                            trim($addressField($address, 'postcode').' '.$addressField($address, 'city')),
                            $state === null ? null : core()->state_name($state, $country),
                            $country === null ? null : core()->country_name($country),
                        ], fn ($line) => $line !== null && $line !== ''));
                    };

                    $billingLines = $addressLines($quote->billing_address);

                    $shippingLines = $addressLines($quote->shipping_address);
                @endphp

                <!-- Billing & Shipping Address -->
                <table class="{{ $direction }}">
                    <thead>
                        <tr>
                            @if ($quote->billing_address)
                                <th style="width: 50%;">
                                    <b>
                                        @lang('admin::app.quotes.index.pdf.billing-address')
                                    </b>
                                </th>
                            @endif

                            @if ($quote->shipping_address)
                                <th style="width: 50%">
                                    <b>
                                        @lang('admin::app.quotes.index.pdf.shipping-address')
                                    </b>
                                </th>
                            @endif
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            @if ($quote->billing_address)
                                <td style="width: 50%">
                                    @foreach ($billingLines as $line)
                                        <div>{{ $line }}</div>
                                    @endforeach
                                </td>
                            @endif

                            @if ($quote->shipping_address)
                                <td style="width: 50%">
                                    @foreach ($shippingLines as $line)
                                        <div>{{ $line }}</div>
                                    @endforeach
                                </td>
                            @endif
                        </tr>
                    </tbody>
                </table>

                <!-- Description -->
                @if ($quote->description)
                    <table class="{{ $direction }}">
                        <thead>
                            <tr>
                                <th>
                                    <b>
                                        @lang('admin::app.quotes.index.pdf.description')
                                    </b>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 9px 18px;">
                                    {{ $quote->description }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif

                <!-- Items -->
                <div class="items">
                    <table class="{{ $direction }}">
                        <thead>
                            <tr>
                                <th>
                                    @lang('admin::app.quotes.index.pdf.sku')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.product-name')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.price')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.quantity')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.amount')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.discount')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.tax')
                                </th>

                                <th>
                                    @lang('admin::app.quotes.index.pdf.grand-total')
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($quote->items as $item)
                                <tr>
                                    <td>{{ $item->sku }}</td>

                                    <td>
                                        {{ $item->name }}
                                    </td>

                                    <td>{!! core()->formatBasePrice($item->price) !!}</td>

                                    <td class="text-center">{{ $item->quantity }}</td>

                                    <td class="text-center">{!! core()->formatBasePrice($item->total) !!}</td>

                                    <td class="text-center">{!! core()->formatBasePrice($item->discount_amount) !!}</td>

                                    <td class="text-center">{!! core()->formatBasePrice($item->tax_amount) !!}</td>
                                    
                                    <td class="text-center">{!! core()->formatBasePrice($item->total + $item->tax_amount - $item->discount_amount) !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

               <!-- Summary Table -->
                @php
                    /**
                     * The middle column of the totals block is a separator, and it shipped as a
                     * hardcoded `-`. That reads as a label/amount divider in the left-to-right
                     * document, but in the mirrored row it lands between an Arabic label and a
                     * Latin-set amount and reads as a stray dash — the reported
                     * `المجموع الفرعي - 87,500.00 US$`. Arabic separates a label from its value with
                     * the `:` the information block above already uses, so that is what a
                     * right-to-left document gets. The English document keeps its shipped hyphen,
                     * because its bytes are the regression gate for this template.
                     */
                    $totalsSeparator = $direction === 'rtl' ? ':' : '-';
                @endphp

                <div class="summary">
                    <table class="{{ $direction }}">
                        <tbody>
                            <tr>
                                <td>@lang('admin::app.quotes.index.pdf.sub-total')</td>
                                <td>{{ $totalsSeparator }}</td>
                                <td>{!! core()->formatBasePrice($quote->sub_total) !!}</td>
                            </tr>
        
                            <tr>
                                <td>@lang('admin::app.quotes.index.pdf.tax')</td>
                                <td>{{ $totalsSeparator }}</td>
                                <td>{!! core()->formatBasePrice($quote->tax_amount) !!}</td>
                            </tr>
        
                            <tr>
                                <td>@lang('admin::app.quotes.index.pdf.discount')</td>
                                <td>{{ $totalsSeparator }}</td>
                                <td>{!! core()->formatBasePrice($quote->discount_amount) !!}</td>
                            </tr>
        
                            <tr>
                                <td>@lang('admin::app.quotes.index.pdf.adjustment')</td>
                                <td>{{ $totalsSeparator }}</td>
                                <td>{!! core()->formatBasePrice($quote->adjustment_amount) !!}</td>
                            </tr>
        
                            <tr>
                                <td><strong>@lang('admin::app.quotes.index.pdf.grand-total')</strong></td>
                                <td><strong>{{ $totalsSeparator }}</strong></td>
                                <td><strong>{!! core()->formatBasePrice($quote->grand_total) !!}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>
