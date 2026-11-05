<?php

use Database\Seeders\Demo\DemoSalesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs the two seeded demo values that the shipped validation rules reject, so an installation
 * that was seeded before this change can open its own demo quotes and save them untouched.
 *
 * Both corrections are the same story: the demo data was written under a laxer rule than the one it
 * is later validated against, so the record the form loads already fails the save it is about to
 * make. Neither correction widens a validation rule; each brings the stored row in line with a rule
 * that is intended.
 *
 * 1. `quotes.adjustment_amount` is the one money field a negative belongs on, and the demo tenant's
 *    third quote stores `-1500.0000`. It was seeded as a `decimal` attribute, and `AttributeForm`
 *    maps `decimal` on a `price` attribute to the non-negative `Decimal` rule, so re-saving that
 *    quote failed with "must be a decimal" on a value the form itself had loaded. The attribute now
 *    carries `validation = 'signed_decimal'`, which selects `Webkul\Core\Contracts\Validations\
 *    SignedDecimal` -- for that attribute only. Every other `price` attribute keeps the
 *    non-negative rule.
 *
 * 2. A quote's `billing_address` is a required `address` attribute, and `AttributeForm` requires its
 *    `state` whenever the chosen country has rows in `country_states`. Egypt's 27 governorates
 *    arrived in `2026_11_05_140000_seed_egypt_governorates_arabic.php`, so every demo quote --
 *    Egypt, region `''` -- became impossible to save untouched. The seeded address gains the
 *    governorate of the city it already carries, taken from `DemoSalesSeeder::GOVERNORATE_CODES`
 *    rather than duplicated here, so the seeder and this repair can never disagree.
 *
 * Matching, and what makes a second run a no-op:
 *
 * - The attribute is matched on its seeded identity: `entity_type`, `code`, `type = 'price'`,
 *   `is_user_defined = 0` *and* the `decimal` value it currently holds. An attribute a client
 *   created is `is_user_defined = 1` and is never touched; an attribute already switched to
 *   `signed_decimal` does not match again, so `up()` twice changes nothing.
 * - Each address is matched on holding the seeded empty region (`state` exactly `''`) for country
 *   `EG` in a city `GOVERNORATE_CODES` knows. An address a client saved -- a region, or a country
 *   this migration has no governorate list for -- is skipped and left exactly as it is. After the
 *   first run no seeded address still holds `''`, so nothing matches again.
 *
 * `shipping_address` is repaired alongside `billing_address` because the controller copies billing
 * over shipping on every save (`syncShippingAddressWithBilling`); leaving the seeded shipping copies
 * empty would just be undone by the next save. `organizations.address` is deliberately not touched:
 * that attribute is not required, so `AttributeForm` skips its address block entirely and an empty
 * region there is not a validation failure.
 */
return new class extends Migration
{
    /**
     * The validation value that selects the signed rule, and the value it replaces.
     */
    protected const SIGNED_VALIDATION = 'signed_decimal';

    protected const UNSIGNED_VALIDATION = 'decimal';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->allowSignedAdjustment();

        $this->fillSeededGovernorates();
    }

    /**
     * Reverse the migrations.
     *
     * What this restores: the attribute back to `decimal` and the seeded addresses back to the empty
     * region, both matched on what `up()` writes, so a row that was never through `up()` is skipped.
     *
     * What it cannot restore, stated plainly:
     *
     * - A region the client saved *after* `up()` -- including the same governorate code `up()` would
     *   write, and including one saved on a different city of the same map entry -- is cleared back to
     *   `''` along with the ones `up()` wrote. There is no record of which was which, and clearing the
     *   seeded empty value is the only state this migration can name.
     * - An attribute switched to `signed_decimal` by hand after `up()` returns to `decimal`, and a
     *   quote holding a negative adjustment then fails to save again. That is the rule as it was.
     */
    public function down(): void
    {
        $this->restoreUnsignedAdjustment();

        $this->clearSeededGovernorates();
    }

    /**
     * Point the seeded quote adjustment at the signed decimal rule.
     */
    protected function allowSignedAdjustment(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'quotes')
            ->where('code', 'adjustment_amount')
            ->where('type', 'price')
            ->where('is_user_defined', 0)
            ->where('validation', self::UNSIGNED_VALIDATION)
            ->update(['validation' => self::SIGNED_VALIDATION]);
    }

    /**
     * Put the seeded quote adjustment back on the non-negative rule.
     */
    protected function restoreUnsignedAdjustment(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'quotes')
            ->where('code', 'adjustment_amount')
            ->where('type', 'price')
            ->where('is_user_defined', 0)
            ->where('validation', self::SIGNED_VALIDATION)
            ->update(['validation' => self::UNSIGNED_VALIDATION]);
    }

    /**
     * Give every seeded quote address the governorate of the city it carries.
     */
    protected function fillSeededGovernorates(): void
    {
        $this->rewriteAddresses(fn (array $address): ?array => $this->withGovernorate($address));
    }

    /**
     * Return the seeded addresses to the empty region.
     */
    protected function clearSeededGovernorates(): void
    {
        $this->rewriteAddresses(fn (array $address): ?array => $this->withoutGovernorate($address));
    }

    /**
     * Rewrite both address columns of every quote through the given change.
     *
     * A change returns the replacement address, or `null` to leave that column alone. A quote whose
     * columns both return `null` is not written at all, so a run over an already-repaired database
     * issues no UPDATE.
     *
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $change
     */
    protected function rewriteAddresses(callable $change): void
    {
        $quotes = DB::table('quotes')
            ->select('id', 'billing_address', 'shipping_address')
            ->orderBy('id')
            ->get();

        foreach ($quotes as $quote) {
            $updates = [];

            foreach (['billing_address', 'shipping_address'] as $column) {
                $address = $this->decode($quote->$column);

                if ($address === null) {
                    continue;
                }

                $changed = $change($address);

                if ($changed !== null) {
                    $updates[$column] = $this->encode($changed);
                }
            }

            if ($updates !== []) {
                DB::table('quotes')->where('id', $quote->id)->update($updates);
            }
        }
    }

    /**
     * The address with its governorate filled in, or `null` when this migration must leave it alone.
     *
     * "Leave it alone" covers every address that is not a seeded one: a region somebody saved, a
     * country this migration has no governorate list for, and a city the demo fixtures do not name.
     *
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>|null
     */
    protected function withGovernorate(array $address): ?array
    {
        if (($address['state'] ?? null) !== '') {
            return null;
        }

        $code = $this->governorateCode($address);

        if ($code === null) {
            return null;
        }

        $address['state'] = $code;

        return $address;
    }

    /**
     * The address with the governorate this migration wrote removed, or `null` when it is not ours.
     *
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>|null
     */
    protected function withoutGovernorate(array $address): ?array
    {
        $code = $this->governorateCode($address);

        if ($code === null || ($address['state'] ?? null) !== $code) {
            return null;
        }

        $address['state'] = '';

        return $address;
    }

    /**
     * The governorate code the address's own country and city resolve to, if this tree names one.
     *
     * @param  array<string, mixed>  $address
     */
    protected function governorateCode(array $address): ?string
    {
        if (($address['country'] ?? null) !== 'EG') {
            return null;
        }

        return DemoSalesSeeder::GOVERNORATE_CODES[$address['city'] ?? ''] ?? null;
    }

    /**
     * Decode one JSON address column, or `null` when it is absent or not an address.
     *
     * @return array<string, mixed>|null
     */
    protected function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Encode an address back into the JSON column.
     *
     * @param  array<string, mixed>  $address
     */
    protected function encode(array $address): string
    {
        return json_encode($address, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
