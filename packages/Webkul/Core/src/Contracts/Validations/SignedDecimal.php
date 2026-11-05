<?php

namespace Webkul\Core\Contracts\Validations;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A decimal that may carry a leading sign, for the one money field where a negative is meant.
 *
 * `Decimal` refuses a sign, and for almost every money field that is exactly right: a price, a
 * discount, a tax, a total or a lead value is never negative. The quote's `adjustment_amount` is the
 * exception the product actually uses — it exists to take money *off* an offer, the demo tenant's
 * own quote holds `-1500.0000`, and the browser-side `decimal` rule in `vee-validate.js` already
 * accepts `^[-+]?…`. So the server rejected a value the form allowed, and a stored quote could not be
 * re-saved untouched.
 *
 * This rule closes that gap without touching `Decimal`: an attribute only reaches it by carrying
 * `validation = 'signed_decimal'`, which `AttributeForm` maps to this class for a `price` attribute
 * and nothing else maps at all. Every other money field therefore keeps the non-negative rule it
 * had, which is the point — widening `Decimal` would have let a negative price, discount or lead
 * value through everywhere at once.
 *
 * The shape is `Decimal`'s: an optional integer part, an optional fraction of one to four decimals,
 * and nothing else. On top of it, an optional leading `-` or `+` — the plus because the browser-side
 * rule accepts one, and a server that rejects what its own form accepts is the defect being fixed.
 * The lookahead is what keeps the sign meaningful: without it the `\d*` integer part would let a bare
 * `-` through, and `-` is not a number MySQL can store in a decimal column.
 */
class SignedDecimal implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /**
         * The value arrives as the request carried it, so an array reaches `preg_match()` unchanged
         * and throws an uncaught TypeError — a 500 rather than a validation failure. A non-scalar
         * value is not a decimal. Same guard as `Decimal`, for the same reason.
         */
        if (! is_scalar($value)) {
            $fail(trans('admin::app.validations.message.signed_decimal', ['attribute' => $attribute]));

            return;
        }

        if (! preg_match('/^[-+]?(?=\d|\.\d)\d*(\.\d{1,4})?$/', (string) $value)) {
            $fail(trans('admin::app.validations.message.signed_decimal', ['attribute' => $attribute]));
        }
    }
}
