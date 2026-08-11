<?php
/**
 * Money is carried as an integer number of minor units, because that is the only
 * representation Stripe accepts or returns. Storing a decimal would put a
 * multiply-and-round on every outbound call and a divide on every webhook, which
 * is two rounding boundaries on a figure that has to reconcile exactly with
 * Stripe's ledger.
 *
 * to_cents() refusing a value rather than coercing it is load-bearing rather than
 * defensive: this database runs with an empty sql_mode, so MySQL will quietly
 * store 0 for a non-numeric amount and the invoice bills nothing with no error
 * anywhere. The refusal here is the only thing that catches it.
 */
class Money {

    /**
     * Parse a typed amount into minor units, or null when it is not a valid
     * amount. Deliberately not (int) round($value * 100): 1.005 has no exact
     * binary form, so that expression yields 100 on some builds and 101 on
     * others. Splitting the string keeps the arithmetic in integers.
     */
    public static function to_cents($raw): ?int
    {
        $value = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
        $value = str_replace(array(',', ' ', "\xc2\xa0", '$'), '', trim($value));

        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(-?)(\d{1,9})(?:\.(\d{1,2}))?$/', $value, $parts)) {
            return null;
        }

        $sign     = ($parts[1] === '-' ? -1 : 1);
        $whole    = (int) $parts[2];
        $fraction = isset($parts[3]) ? (int) str_pad($parts[3], 2, '0') : 0;

        return $sign * ($whole * 100 + $fraction);
    }

    /**
     * Quantity is held to three decimal places so a half hour is exact. The line
     * total is rounded here, once, and stored - so the invoice total is the sum
     * of integers that were each rounded exactly once, and cannot drift from
     * what Stripe was sent.
     */
    public static function line_amount($quantity_milli, $unit_amount_cents): int
    {
        $quantity_milli    = (int) $quantity_milli;
        $unit_amount_cents = (int) $unit_amount_cents;

        if ($quantity_milli === 0 || $unit_amount_cents === 0) {
            return 0;
        }

        $product = $quantity_milli * $unit_amount_cents;

        if ($product < 0) {
            return -intdiv(-$product + 500, 1000);
        }

        return intdiv($product + 500, 1000);
    }

    /** Quantity as typed into minor-of-a-unit thousandths, or null when invalid. */
    public static function to_quantity_milli($raw): ?int
    {
        $value = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
        $value = str_replace(array(',', ' '), '', trim($value));

        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(\d{1,5})(?:\.(\d{1,3}))?$/', $value, $parts)) {
            return null;
        }

        $whole    = (int) $parts[1];
        $fraction = isset($parts[2]) ? (int) str_pad($parts[2], 3, '0') : 0;
        $milli    = $whole * 1000 + $fraction;

        if ($milli <= 0) {
            return null;
        }

        return $milli;
    }

    /**
     * The currency code leads rather than a symbol, because clients are billed in
     * their own currency and $ alone would be ambiguous across a list holding USD
     * and CHF at once. Matches the CONCAT() the list queries use, so a figure
     * rendered by PHP and one rendered by SQL read identically.
     */
    public static function format($cents, $currency): string
    {
        return strtoupper((string) $currency) . ' ' . number_format(((int) $cents) / 100, 2);
    }

    /** Display form for a quantity held in thousandths, trimmed of empty decimals. */
    public static function format_quantity($quantity_milli): string
    {
        $quantity_milli = (int) $quantity_milli;

        if ($quantity_milli % 1000 === 0) {
            return (string) intdiv($quantity_milli, 1000);
        }

        return rtrim(rtrim(number_format($quantity_milli / 1000, 3), '0'), '.');
    }

}
