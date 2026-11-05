<?php

namespace Webkul\Admin\DataGrids\Concerns;

/**
 * Renders a money column so the grid cannot break the currency token in half.
 *
 * The desktop view of a grid is a CSS grid of equally wide columns -- 14 columns at 63px on a 1280px
 * screen, 86px on a 1600px one -- and its cells carry `overflow-wrap: break-word`. A money value is
 * the one cell value with no break opportunity inside it: `formatBasePrice()` joins the amount and
 * the symbol with a no-break space, so the browser has to break the value at an arbitrary point and
 * lands inside the token. Measured on this build with `APP_LOCALE=ar` and `APP_CURRENCY=USD`, on the
 * leads grid at a 1600px viewport, where the value column is 86.1px wide, six of the ten rows broke
 * as `12,000.00 U` over `S$`, and one as `11,000.00 US` over `$`.
 *
 * What is rendered instead keeps the value on one line when it fits and breaks it where a money value
 * should break when it does not:
 *
 * - the no-break space becomes an ordinary space, which is the break the cell takes first;
 * - the token is wrapped in `whitespace-nowrap`, so it can never be split, and an amount too long for
 *   the column still wraps the way the grid already wraps any long value -- the digits break and the
 *   symbol stays whole on the next line;
 * - the whole value stays inside `<bdi dir="ltr">`, which is the isolation the datagrid gives a plain
 *   value through `isolateCell()` in `components/datagrid/table.blade.php`. A value that carries
 *   markup is passed through untouched, so the isolation has to be part of this markup or the amount
 *   would flip to the right-to-left side of its cell.
 *
 * The replacement is keyed on the symbol `formatBasePrice()` itself used, taken from the same core
 * helper, so it stays correct when the locale stops isolating the symbol -- an Arabic-script symbol
 * such as the Saudi riyal is never wrapped in an isolate, and an English interface writes the symbol
 * before the amount with no no-break space at all. Neither shape matches, and both are returned
 * unchanged.
 *
 * A row is not exported through this method -- `Exports/DataGridExport` maps the raw query columns --
 * so no markup reaches a spreadsheet, and sorting and filtering resolve against the column's database
 * column, never against this rendered value.
 */
trait FormatsMoneyCells
{
    /**
     * Format a money value for a grid cell, protecting the currency token from being split.
     */
    protected function formatMoneyCell(mixed $amount): string
    {
        $formatted = core()->formatBasePrice($amount);

        $symbol = core()->currencySymbol(config('app.currency'));

        $space = "\u{00A0}";

        if ($symbol === '' || ! str_contains($formatted, $space.$symbol)) {
            return $formatted;
        }

        return '<bdi dir="ltr">'.str_replace(
            $space.$symbol,
            ' <span class="whitespace-nowrap">'.$symbol.'</span>',
            $formatted
        ).'</bdi>';
    }
}
