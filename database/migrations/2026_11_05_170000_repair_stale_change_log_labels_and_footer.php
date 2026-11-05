<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs the two display defects a logged-in walk of the Arabic admin found in stored values: the
 * frozen labels inside the change log, and the English footer credit.
 *
 * Both are the same story as `2026_11_05_150000_translate_activity_log_to_arabic.php` — the change
 * log stores a *rendering*, not a value — so the defects survive a code fix. `LogsActivity` writes
 * `additional` once, at write time, and the panel below renders `old.label` and `new.label` verbatim
 * (Vue template interpolation, `admin/.../components/activities/index.blade.php`). Fixing the
 * formatter changes what is written from now on; it cannot change a row already written.
 *
 * What the rows hold, measured on the reference instance (463 `type = 'system'` rows, all written in
 * one two-second window on `2026-11-02 01:57:11–13` by a demo-seeding run while the instance was still
 * `en`):
 *
 * 1. 72 labels — 36 rows — carry a money rendering from before the currency work: `$0.00`,
 *    `$45,000.00`, `$120,000.00`. The product now writes `US$`, because `Core::formatBasePrice()`
 *    names the currency through `plainCurrencySymbol()` and isolates a Latin token on a right-to-left
 *    surface. So the same log reads `$45,000.00` in a panel whose every other price reads `US$`.
 *    `2026_11_05_150000_…` deliberately skipped these: a price label is not English *because of the
 *    locale*, so there was nothing for that migration to translate. It is stale because of the
 *    renderer, which is this migration's job.
 * 2. 27 labels carry a date from the same writer, in the shape `D M d, Y` produced —
 *    `جمعة نوفمبر 27, 2026`, a weekday and a Latin comma. The formatter now produces `d M Y`
 *    (`AttributeValueRepository::getAttributeLabel()`), the shape every grid, the record view and the
 *    `2 نوفمبر 2026 في 1:57 ص` line on the same screen use, so the stale rows would otherwise keep
 *    printing the shape the product no longer has.
 * 3. The footer row `general.settings.footer.label`, when it still holds the English credit this
 *    product shipped. The footer renders a *stored* setting (`core()->getConfigData()` →
 *    `core()->sanitizeHtml()`), so it cannot follow the interface locale the way the login page's
 *    `admin::app.layouts.powered-by.description` does: an Arabic install read `Powered by … Sijilna.`
 *    at the bottom of the screen while the login page two clicks away read `مدعوم من Sijilna.`. New
 *    installs now seed the Arabic wording (`Sijilna\Brand\Brand::FOOTER_CREDIT`); this statement
 *    brings an existing installation's row to the same value.
 *
 * Matching, and what makes a second run a no-op:
 *
 * - Only `type = 'system'` rows are read. Every other row is a title somebody typed and a comment
 *   somebody wrote; no renderer produced them, so they are never touched.
 * - A label moves only when it is *byte-for-byte* one of the two renderings the writer produced for
 *   that row's own `value` — the stale one or the current one — and never when it already equals the
 *   target. Nothing is pattern-matched loosely: `$45,000.00` is recognised because
 *   `'$'.number_format(45000, 2)` is exactly that string, not because it looks like money. After the
 *   first run every rewritten label equals its target, so nothing matches again.
 * - A value that cannot be resolved is left exactly as it is: a label on a date that no longer parses,
 *   a money label whose base currency renders with a symbol other than `$` (so its own stale
 *   rendering cannot be named), and anything that is not a price or a date at all.
 * - The date re-render runs only on an Arabic surface. The shape it matches is Arabic, so the row
 *   exists only because an Arabic surface wrote it; rewriting it into another locale's month names is
 *   not this migration's call.
 * - The footer moves only while the row still holds the shipped English credit *as the repository
 *   stored it* (`core()->sanitizeHtml()`, which is what `CoreConfigRepository` applies on the way in,
 *   so the stored form carries `rel="noreferrer noopener"` and normalised inline styles). A row an
 *   operator edited does not match and is never rewritten. A row that is absent needs no write: the
 *   config default is the Arabic credit now.
 * - `value`, `comment`, `type`, `is_done`, `user_id` and every timestamp are untouched. `additional`
 *   is rewritten only through its own decoded array, so its sibling keys survive, and the writer's
 *   `attribute` name inside it is kept — the timeline renders `old.label` and `new.label` only.
 *
 * UPDATE only: no DDL, no deletes, no inserts, no raw SQL, no string interpolation. An installation
 * that never moved to Arabic and never lost the currency rendering is a complete no-op.
 */
return new class extends Migration
{
    /**
     * The configuration key of the surface locale.
     *
     * Read rather than assumed, and read from the same key `Webkul\Admin\Http\Middleware\Locale`
     * sets `app()->setLocale()` from, so a label is re-rendered in the language the screen it belongs
     * to is rendered in.
     */
    protected const SURFACE_LOCALE = 'general.general.locale_settings.locale';

    /**
     * The one locale whose date labels this migration re-renders, and the reason it is named rather
     * than read from `self::SURFACE_LOCALE` at the point of use: the guard and the rendering have to
     * agree, and a guard of "the label is Arabic" against a rendering in some third locale would be
     * two different decisions.
     */
    protected const ARABIC_LOCALE = 'ar';

    /**
     * The footer's configuration key.
     */
    protected const FOOTER_CODE = 'general.settings.footer.label';

    /**
     * The English footer credit this product shipped before the wording became Arabic. Repeated here
     * rather than read from `Sijilna\Brand\Brand::FOOTER_CREDIT_EN` because a migration has to keep
     * working after `Brand` moves, is renamed or is no longer loaded in a console context; the
     * constant and this string are kept in step by hand, and the value in it is what an untouched row
     * in an existing installation holds.
     */
    protected const FOOTER_EN = 'Powered by <span style="color: rgb(11, 60, 93);"><a href="https://sijilna.com" target="_blank">Sijilna</a></span>.';

    /**
     * The Arabic footer credit this product ships: the wording the login page already reads.
     */
    protected const FOOTER_AR = 'مدعوم من <span style="color: rgb(11, 60, 93);"><a href="https://sijilna.com" target="_blank">Sijilna</a></span>.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->relabel(false);

        $this->relabelFooter(false);
    }

    /**
     * Reverse the migrations.
     *
     * What this restores: the stale rendering of every label — `$15,000.00` back from
     * `15,000.00 US$`, `جمعة نوفمبر 27, 2026` back from `27 نوفمبر 2026` — matched on the label still
     * being exactly what `up()` wrote for that row's own `value`, and the English footer credit for a
     * footer row that still holds the Arabic one.
     *
     * What it cannot restore, stated plainly:
     *
     * - A label written by the product *after* `up()` ran reads the same as one `up()` rewrote, so it
     *   is reverted with them. There is no record of which was which, and both are the same rendering
     *   of the same value.
     * - Restoring a money label assumes the base currency whose symbol the stale label carried — `$`,
     *   which is the only stale shape `up()` ever matched. If the base currency changed after `up()`,
     *   that row's label no longer matches what the renderer produces and is left as it is.
     * - Whether the Arabic footer it finds was written by `up()` or typed by hand afterwards. Both
     *   read the same, so both go back to the English credit.
     */
    public function down(): void
    {
        $this->relabel(true);

        $this->relabelFooter(true);
    }

    /**
     * Re-render every stale label, in the `up()` or the `down()` direction.
     */
    protected function relabel(bool $reverse): void
    {
        $locale = $this->surfaceLocale();

        $previous = app()->getLocale();

        /**
         * `Core::formatBasePrice()` and Carbon's `translatedFormat()` both read the active locale, and
         * the label has to be the one the screen shows. The previous locale is put back afterwards so
         * that whichever migration runs next in this process is not handed a changed interface
         * language.
         */
        app()->setLocale($locale);

        try {
            DB::table('activities')
                ->where('type', 'system')
                ->whereNotNull('additional')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($reverse, $locale) {
                    foreach ($rows as $row) {
                        $this->rewrite($row, $reverse, $locale);
                    }
                });
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * Rewrite one activity row's two labels, or leave the row exactly as it is.
     */
    protected function rewrite(object $row, bool $reverse, string $locale): void
    {
        /*
         * A payload that is not a JSON object is not something this migration can reason about, and
         * half-reading it would be a guess. The row is skipped whole.
         */
        $additional = json_decode((string) $row->additional, true);

        if (! is_array($additional)) {
            return;
        }

        $changed = false;

        foreach (['new', 'old'] as $side) {
            if (! isset($additional[$side]) || ! is_array($additional[$side])) {
                continue;
            }

            $label = $additional[$side]['label'] ?? null;

            if (! is_string($label) || $label === '') {
                continue;
            }

            [$stale, $current] = $this->renderings($label, $additional[$side]['value'] ?? null, $locale);

            $from = $reverse ? $current : $stale;

            $to = $reverse ? $stale : $current;

            if ($from === null || $to === null || $label !== $from || $to === $label) {
                continue;
            }

            $additional[$side]['label'] = $to;

            $changed = true;
        }

        if (! $changed) {
            return;
        }

        DB::table('activities')
            ->where('id', $row->id)
            ->update(['additional' => json_encode($additional, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * The stale and the current rendering of one label, in that order, or `[null, null]` when the
     * label is not one this migration can name.
     *
     * The pair is only returned when the label actually *is* one of the two — the caller then compares
     * byte-for-byte — so a text label that happens to read like a number, a price label on a value that
     * is no longer numeric, and a date label on a value that no longer parses all fall through to
     * `[null, null]` and are left alone.
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function renderings(string $label, mixed $value, string $locale): array
    {
        if ($this->isPriceValue($value)) {
            /*
             * The stale rendering is the dollar string the pre-currency writer produced for this
             * value; the current one is the product's own renderer, so a label can never drift from
             * what a fresh write stores. On an English surface the two are the same string and the
             * row does not move at all, which is what keeps an `en` installation untouched.
             */
            $stale = '$'.number_format((float) $value, 2);

            $current = core()->formatBasePrice((float) $value);

            if ($label === $stale || $label === $current) {
                return [$stale, $current];
            }
        }

        if ($locale === self::ARABIC_LOCALE) {
            $stale = $this->formatDate($value, 'D M d, Y');

            $current = $this->formatDate($value, 'd M Y');

            if ($stale !== null && $current !== null && ($label === $stale || $label === $current)) {
                return [$stale, $current];
            }
        }

        return [null, null];
    }

    /**
     * Is this stored value one a price label can be rendered from?
     *
     * A price attribute stores a number, and `AttributeValueRepository::save()` writes an empty one as
     * `null`, which `Core::formatBasePrice()` renders as `0.00`. A date stores a string, so it fails
     * this test and is handled by {@see self::renderings()} below — the two branches cannot claim the
     * same value.
     */
    protected function isPriceValue(mixed $value): bool
    {
        return $value === null || is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    /**
     * One date value formatted the way the writer formats it, or null when the value is not a date.
     *
     * This repeats the pattern and the locale from
     * `Webkul\Attribute\Repositories\AttributeValueRepository::getAttributeLabel()` instead of calling
     * it: a migration has to keep working after that method moves, changes signature or needs a
     * container it may no longer be given.
     */
    protected function formatDate(mixed $value, string $format): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->locale(self::ARABIC_LOCALE)->translatedFormat($format);
    }

    /**
     * Bring the footer credit to the shipped Arabic wording, forward or back.
     */
    protected function relabelFooter(bool $reverse): void
    {
        $from = core()->sanitizeHtml($reverse ? self::FOOTER_AR : self::FOOTER_EN);

        $to = core()->sanitizeHtml($reverse ? self::FOOTER_EN : self::FOOTER_AR);

        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        DB::table('core_config')
            ->where('code', self::FOOTER_CODE)
            ->where('value', $from)
            ->update(['value' => $to]);
    }

    /**
     * The interface locale — the same expression `Webkul\Admin\Http\Middleware\Locale` uses.
     */
    protected function surfaceLocale(): string
    {
        $configured = core()->getConfigData(self::SURFACE_LOCALE);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) config('app.locale');
    }
};
