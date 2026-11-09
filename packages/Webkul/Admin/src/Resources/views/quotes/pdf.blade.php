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

            /*
                The document's wordmark.

                The header carried only the document title, so the quote a buyer receives and
                forwards internally named no product at all, while the admin header, the login
                screen and the branded error pages all carry the wordmark.

                It is stacked above the title rather than absolutely positioned in a corner: the
                mark, the title, the information block and the address tables are then separate
                blocks in normal flow, so no padding or font-size change can slide the mark onto
                the date, the quote number or the customer block. The stock `.logo-container` rule
                above is absolute and unused, and mPDF's absolute box model is the one part of it
                this template cannot verify, which is why it is not revived here.

                Width in px, height auto: both renderers keep the SVG's 256-unit height and the
                600x256 (ar: 560x256) aspect, and 150px gives a mark about 112pt wide — legible on
                a printed A4 page without competing with the 24px title under it.
            */
            .brand-wordmark {
                text-align: center;
                margin-bottom: 6px;
                line-height: 0;
            }

            .brand-wordmark img {
                width: 150px;
                height: auto;
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

            /*
                Line-item column widths.

                The product-name column used to be crushed to its narrowest allowed width: mPDF
                lays an over-constrained table out by shrinking each column towards its minimum,
                and a multi-word cell has a huge range between that minimum (its widest single
                word) and its natural width, while a figure column's minimum is already its natural
                width. So every column kept its size and the one elastic column absorbed the whole
                shortfall. The demo dataset's longest name, `رخصة نظام إدارة العملاء — أساسية`, needs
                97.33pt of glyphs plus five word spaces, but the column's content box measured
                26pt — narrower than most of the words in it — and the name printed as six stacked
                single words.

                Two changes, measured against the 577.28pt table (A4 minus the 12px page padding):

                  - the six figure columns give up 12px of their 36px horizontal padding each, 90pt
                    in all, which is the space the name needs. Their content is digits and a
                    currency token, so the padding bought nothing but air;
                  - every column states its width, so the layout no longer has to guess which one
                    should absorb the shortfall. The product-name column is given the remainder,
                    ~173pt of content, which is the ~108pt the longest name needs plus room for the
                    next-longest one.

                Fonts and figure values are untouched, and the columns still sum to the same table
                width, so no total moves.
            */
            .items th {
                padding: 6px 9px;
            }

            .items td {
                padding: 9px 9px;
            }

            .items th.col-product-name,
            .items td.col-product-name {
                padding-left: 18px;
                padding-right: 18px;
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
                @php
                    /**
                     * The brand wordmark, from the one place this fork keeps its marks: the brand
                     * package's `resources/assets/`, which is what
                     * `App\Providers\BrandingServiceProvider` serves the in-page wordmarks from and
                     * what its `Sijilna\Brand\Brand` constants name. Resolved with `base_path()`,
                     * so it is the checkout's own path on any host — not an absolute path baked for
                     * one machine and not a URL.
                     *
                     * A local path, not `route('sijilna.asset')`: the English document is rendered by
                     * dompdf with remote fetching off, and the Arabic one by mPDF, which would make
                     * the PDF renderer issue an HTTP request to the application it is running
                     * inside. Probed in both engines: each renders the SVG from a file path.
                     *
                     * The SVG and not `Brand::PUBLISHED_LOGO_PATH`: that raster is cut on a
                     * `#FAF7F1` paper surface for the dashboard report, so it prints a cream block
                     * on this white page, and it is the Latin lockup only. The SVGs are transparent
                     * and come in a per-script cut, chosen the same way the login, mail and error
                     * blades choose theirs.
                     *
                     * `is_file()` keeps a checkout without the brand package rendering the
                     * document it rendered before this header carried a mark, instead of a
                     * broken-image placeholder.
                     */
                    $brandWordmark = base_path(
                        'packages/Sijilna/Brand/resources/assets/'
                        .(in_array($locale, ['ar', 'fa']) ? 'sijilna-wordmark-ar.svg' : 'sijilna-wordmark.svg')
                    );
                @endphp

                @if (is_file($brandWordmark))
                    <div class="brand-wordmark">
                        <img
                            src="{{ $brandWordmark }}"
                            alt="{{ config('sijilna.name', 'Sijilna') }}"
                        >
                    </div>
                @endif

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
                                <th style="width: 12%;">
                                    @lang('admin::app.quotes.index.pdf.sku')
                                </th>

                                <th
                                    class="col-product-name"
                                    style="width: 31%;"
                                >
                                    @lang('admin::app.quotes.index.pdf.product-name')
                                </th>

                                <th style="width: 11.5%;">
                                    @lang('admin::app.quotes.index.pdf.price')
                                </th>

                                <th style="width: 5.5%;">
                                    @lang('admin::app.quotes.index.pdf.quantity')
                                </th>

                                <th style="width: 11.5%;">
                                    @lang('admin::app.quotes.index.pdf.amount')
                                </th>

                                <th style="width: 7%;">
                                    @lang('admin::app.quotes.index.pdf.discount')
                                </th>

                                <th style="width: 10%;">
                                    @lang('admin::app.quotes.index.pdf.tax')
                                </th>

                                <th style="width: 11.5%;">
                                    @lang('admin::app.quotes.index.pdf.grand-total')
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($quote->items as $item)
                                <tr>
                                    <td>{{ $item->sku }}</td>

                                    <td class="col-product-name">
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
